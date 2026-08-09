// Vercel serverless function — saves data_sheet.js and uploads images to the GitHub repo
// Required environment variables:
// - GITHUB_TOKEN (personal access token, repo scope)
// - GITHUB_REPO (owner/repo)
// - GITHUB_BRANCH (branch to commit to, default: main)

const GITHUB_API = 'https://api.github.com';

function normalizeDriveImageUrl(url) {
  if (!url || typeof url !== 'string') return url;
  const cleaned = url.trim();
  const m = cleaned.match(/(?:drive\.google\.com\/file\/d\/|drive\.google\.com\/open\?id=|drive\.google\.com\/uc\?id=|docs\.google\.com\/uc\?id=)([a-zA-Z0-9_-]+)/);
  if (m) return `https://drive.google.com/uc?export=view&id=${m[1]}`;
  return cleaned;
}

function isRemoteUrl(url) {
  return typeof url === 'string' && /^https?:\/\//i.test(url);
}

async function downloadImageBuffer(url) {
  url = normalizeDriveImageUrl(url);
  const resp = await fetch(url, { redirect: 'follow' });
  if (!resp.ok) throw new Error(`Failed to download ${url}: ${resp.status}`);
  const contentType = resp.headers.get('content-type') || '';
  const buffer = Buffer.from(await resp.arrayBuffer());
  return { buffer, contentType };
}

async function githubGetFileSha(repo, path, branch, token) {
  const url = `${GITHUB_API}/repos/${repo}/contents/${encodeURIComponent(path)}?ref=${encodeURIComponent(branch)}`;
  const resp = await fetch(url, { headers: { Authorization: `token ${token}`, Accept: 'application/vnd.github.v3+json' } });
  if (resp.status === 200) {
    const j = await resp.json();
    return j.sha;
  }
  return null;
}

async function githubPutFile(repo, path, contentBase64, message, branch, token) {
  const url = `${GITHUB_API}/repos/${repo}/contents/${encodeURIComponent(path)}`;
  const sha = await githubGetFileSha(repo, path, branch, token);
  const body = { message, content: contentBase64, branch };
  if (sha) body.sha = sha;
  const resp = await fetch(url, {
    method: 'PUT',
    headers: { Authorization: `token ${token}`, 'Content-Type': 'application/json', Accept: 'application/vnd.github.v3+json' },
    body: JSON.stringify(body),
  });
  if (!resp.ok) {
    const txt = await resp.text();
    throw new Error(`GitHub upload failed ${resp.status}: ${txt}`);
  }
  return await resp.json();
}

export default async function handler(req, res) {
  if (req.method !== 'POST') return res.status(405).json({ success: false, error: 'Method not allowed' });

  const token = process.env.GITHUB_TOKEN;
  const repo = process.env.GITHUB_REPO;
  const branch = process.env.GITHUB_BRANCH || 'main';

  if (!token || !repo) return res.status(500).json({ success: false, error: 'GITHUB_TOKEN and GITHUB_REPO must be set in environment' });

  const body = req.body;
  const items = Array.isArray(body.items) ? body.items : [];

  const uploadedImages = [];

  try {
    // process images: download remote and upload each to repo under Gambar/
    for (const item of items) {
      if (!item || typeof item !== 'object') continue;
      if (item.image && isRemoteUrl(item.image)) {
        try {
          const { buffer, contentType } = await downloadImageBuffer(item.image);
          // determine extension
          let ext = 'jpg';
          if (contentType.includes('png')) ext = 'png';
          else if (contentType.includes('webp')) ext = 'webp';
          else if (contentType.includes('gif')) ext = 'gif';
          else if (contentType.includes('svg')) ext = 'svg';

          const filename = `img_${Date.now()}_${Math.random().toString(36).slice(2,8)}.${ext}`;
          const path = `Gambar/${filename}`;
          const contentBase64 = buffer.toString('base64');
          await githubPutFile(repo, path, contentBase64, `Add image ${filename}`, branch, token);
          item.image = `/${path}`; // use repo relative path
          uploadedImages.push(path);
        } catch (err) {
          // fallback: normalize Drive url and keep original
          item.image = normalizeDriveImageUrl(item.image);
        }
      } else if (item.image) {
        item.image = normalizeDriveImageUrl(item.image);
      }
    }

    // write data_sheet.js to repo root
    const jsContent = `window.DATA_SHEET = ${JSON.stringify(items, null, 2)};`;
    const jsBase64 = Buffer.from(jsContent).toString('base64');
    await githubPutFile(repo, 'data_sheet.js', jsBase64, 'Update data_sheet.js', branch, token);

    return res.json({ success: true, uploadedImages, path: '/data_sheet.js' });
  } catch (error) {
    return res.status(500).json({ success: false, error: error.message });
  }
}

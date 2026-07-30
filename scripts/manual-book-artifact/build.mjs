// Assembles docs/manual-book/akademik/*.md + images into a single self-contained
// HTML artifact (images inlined as base64 data URIs) for publishing as a Claude Artifact.
// Usage: node scripts/manual-book-artifact/build.mjs
// Output: scripts/manual-book-artifact/dist/manual-book-akademik.html (git-ignored;
// re-run this script any time a chapter or screenshot changes, then publish that file).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..', '..', 'docs', 'manual-book', 'akademik');
const IMG_DIR = path.join(ROOT, 'images');
const OUT_DIR = path.join(__dirname, 'dist');
const OUT = path.join(OUT_DIR, 'manual-book-akademik.html');

const CHAPTERS = [
  { id: 'bab-0', file: '00-setup-lembaga.md', num: '0', label: 'Setup Lembaga', role: 'Admin Yayasan' },
  { id: 'bab-1', file: '01-data-master.md', num: '1', label: 'Data Master Akademik', role: 'Admin Akademik' },
  { id: 'bab-2', file: '02-penjadwalan.md', num: '2', label: 'Penjadwalan', role: 'Admin Akademik' },
  { id: 'bab-3', file: '03-presensi-jurnal.md', num: '3', label: 'Presensi & Jurnal', role: 'Guru' },
  { id: 'bab-4', file: '04-asesmen-nilai.md', num: '4', label: 'Asesmen & Nilai', role: 'Admin Akademik + Guru' },
  { id: 'bab-5', file: '05-rekap-rapor.md', num: '5', label: 'Rekap Rapor', role: 'Admin Akademik' },
  { id: 'bab-6', file: '06-kenaikan-kelas.md', num: '6', label: 'Kenaikan Kelas', role: 'Admin Akademik' },
  { id: 'lampiran', file: 'lampiran-lintas-lembaga.md', num: 'L', label: 'Kalender Nasional & Lintas Lembaga', role: 'Admin Yayasan' },
];

const BR_TOKEN = 'BR';

function imgDataUri(file) {
  const p = path.join(IMG_DIR, file);
  const buf = fs.readFileSync(p);
  return `data:image/png;base64,${buf.toString('base64')}`;
}

function escapeHtml(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Very small, purpose-built markdown -> HTML converter for this manual book's
// consistent structure (headers, bold, images, links, numbered/bulleted lists,
// paragraphs). Not a general-purpose parser.
function mdToHtml(md) {
  const lines = md.split('\n');
  let html = '';
  let i = 0;
  let inUl = false, inOl = false;

  function closeLists() {
    if (inUl) { html += '</ul>\n'; inUl = false; }
    if (inOl) { html += '</ol>\n'; inOl = false; }
  }

  function inline(text) {
    text = escapeHtml(text);
    text = text.split(BR_TOKEN).join('<br>');
    text = text.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, (m, alt, src) => {
      const file = src.replace(/^images\//, '');
      return `<figure class="shot"><img src="${imgDataUri(file)}" alt="${alt}" loading="lazy"><figcaption>${alt}</figcaption></figure>`;
    });
    text = text.replace(/\[([^\]]+)\]\(([^)]+\.md)\)/g, (m, label, href) => {
      const num = href.match(/^0*(\d+)/);
      const anchor = href.startsWith('lampiran') ? 'lampiran' : `bab-${num ? num[1] : ''}`;
      return `<a href="#${anchor}" class="xref">${label}</a>`;
    });
    text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
    return text;
  }

  // Gathers indented continuation lines belonging to the same list item,
  // tolerating blank lines in between as long as an indented line follows.
  // A nested "   - " bullet is rendered as its own soft-broken line (via
  // BR_TOKEN, resolved to <br> only after escaping), not flattened into a
  // run-on sentence.
  function gatherContinuation(start) {
    let j = start;
    const parts = [];
    while (j < lines.length) {
      // Continuation lines are indented by at least 2 spaces (bullets wrap at
      // "- " width, numbered items wrap at "N. " width) — top-level markers
      // always start at column 0, so 2+ spaces is unambiguous here.
      if (/^\s{2,}\S/.test(lines[j])) {
        const trimmed = lines[j].trim();
        parts.push(/^- /.test(trimmed) ? `${BR_TOKEN}• ${trimmed.replace(/^- /, '')}` : trimmed);
        j++;
        continue;
      }
      if (lines[j].trim() === '') {
        let k = j;
        while (k < lines.length && lines[k].trim() === '') k++;
        if (k < lines.length && /^\s{2,}\S/.test(lines[k])) { j = k; continue; }
        break;
      }
      break;
    }
    return { text: parts.join(' '), next: j };
  }

  // True if, skipping blank lines from `from`, the next non-blank line is
  // another top-level marker of the given list type — i.e. a blank line
  // here is just paragraph spacing between items, not a list break.
  function listContinuesAfterBlank(from, markerRe) {
    let k = from;
    while (k < lines.length && lines[k].trim() === '') k++;
    return k < lines.length && markerRe.test(lines[k]);
  }

  while (i < lines.length) {
    const line = lines[i];

    if (/^### /.test(line)) {
      closeLists();
      html += `<h3>${inline(line.replace(/^### /, ''))}</h3>\n`;
    } else if (/^## /.test(line)) {
      closeLists();
      html += `<h2>${inline(line.replace(/^## /, ''))}</h2>\n`;
    } else if (/^# /.test(line)) {
      // top-level chapter title handled separately by caller
    } else if (/^- /.test(line)) {
      if (!inUl) { closeLists(); html += '<ul>\n'; inUl = true; }
      let item = line.replace(/^- /, '');
      const cont = gatherContinuation(i + 1);
      if (cont.text) item += ' ' + cont.text;
      html += `<li>${inline(item)}</li>\n`;
      i = cont.next - 1;
    } else if (/^\d+\. /.test(line)) {
      if (!inOl) { closeLists(); html += '<ol>\n'; inOl = true; }
      let item = line.replace(/^\d+\. /, '');
      const cont = gatherContinuation(i + 1);
      if (cont.text) item += ' ' + cont.text;
      html += `<li>${inline(item)}</li>\n`;
      i = cont.next - 1;
    } else if (line.trim() === '') {
      if (inUl && listContinuesAfterBlank(i + 1, /^- /)) {
        // blank line between two "- " items: keep the list open, add nothing
      } else if (inOl && listContinuesAfterBlank(i + 1, /^\d+\. /)) {
        // blank line between two "N. " items: keep the list open, add nothing
      } else {
        closeLists();
      }
    } else if (/^!\[[^\]]*\]\([^)]+\)\s*$/.test(line.trim())) {
      // Standalone image line: emit the figure directly, not wrapped in <p>
      // (a block-level <figure> inside <p> is invalid HTML).
      closeLists();
      html += `${inline(line.trim())}\n`;
    } else {
      closeLists();
      // Join consecutive soft-wrapped plain-text lines into one paragraph,
      // the way markdown treats a run of non-blank lines with no blank
      // line between them.
      const paraLines = [line];
      let j = i + 1;
      while (
        j < lines.length &&
        lines[j].trim() !== '' &&
        !/^#{1,3} /.test(lines[j]) &&
        !/^- /.test(lines[j]) &&
        !/^\d+\. /.test(lines[j]) &&
        !/^!\[[^\]]*\]\([^)]+\)\s*$/.test(lines[j].trim())
      ) {
        paraLines.push(lines[j]);
        j++;
      }
      html += `<p>${inline(paraLines.join(' '))}</p>\n`;
      i = j - 1;
    }
    i++;
  }
  closeLists();
  return html;
}

function wrapSection(html, heading, cls) {
  const re = new RegExp(`<h2>${heading}</h2>\\n([\\s\\S]*?)(?=<h2>|$)`);
  return html.replace(re, (m, inner) => `<h2>${heading}</h2>\n<div class="${cls}">\n${inner}</div>\n`);
}

const chapterHtmlParts = CHAPTERS.map((ch) => {
  const raw = fs.readFileSync(path.join(ROOT, ch.file), 'utf8');
  const body = raw.replace(/^# .+\n/, ''); // strip the leading H1, we render our own header
  let html = mdToHtml(body);
  html = wrapSection(html, 'Prasyarat', 'callout callout-info');
  html = wrapSection(html, 'Kesalahan umum', 'callout callout-warn');
  return `
  <section class="chapter" id="${ch.id}">
    <div class="chapter-head">
      <span class="chapter-num">${ch.num}</span>
      <div>
        <p class="chapter-eyebrow">${ch.num === 'L' ? 'Lampiran' : `Bab ${ch.num}`}</p>
        <h1>${ch.label}</h1>
        <p class="chapter-role">${ch.role}</p>
      </div>
    </div>
    ${html}
  </section>`;
}).join('\n');

const navHtml = CHAPTERS.map((ch) => `<a href="#${ch.id}" class="nav-link"><span class="nav-num">${ch.num}</span>${ch.label}</a>`).join('\n');

const template = fs.readFileSync(path.join(__dirname, 'template.html'), 'utf8');
const finalHtml = template
  .replace('<!--NAV-->', navHtml)
  .replace('<!--CHAPTERS-->', chapterHtmlParts);

fs.mkdirSync(OUT_DIR, { recursive: true });
fs.writeFileSync(OUT, finalHtml, 'utf8');
console.log('wrote', OUT, (fs.statSync(OUT).size / 1024 / 1024).toFixed(2), 'MB');

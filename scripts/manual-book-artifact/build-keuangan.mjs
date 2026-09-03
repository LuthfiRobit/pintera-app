// scripts/manual-book-artifact/build-keuangan.mjs
// Assembles docs/manual-book/keuangan/*.md + images into a single self-contained
// HTML artifact (images inlined as base64 data URIs) for local preview.
// Usage: node scripts/manual-book-artifact/build-keuangan.mjs
// Output: scripts/manual-book-artifact/dist/manual-book-keuangan.html

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..', '..', 'docs', 'manual-book', 'keuangan');
const IMG_DIR = path.join(ROOT, 'images');
const OUT_DIR = path.join(__dirname, 'dist');
const OUT = path.join(OUT_DIR, 'manual-book-keuangan.html');

const CHAPTERS = [
  { id: 'bab-0', file: '00-konsep-dasar.md', num: '0', label: 'Konsep Dasar Modul Keuangan', role: 'Admin Bendahara & Admin Yayasan' },
  { id: 'bab-1', file: '01-membuat-jenis-tagihan.md', num: '1', label: 'Membuat Jenis Tagihan', role: 'Admin Bendahara Lembaga' },
  { id: 'bab-2', file: '02-target-sasaran-dan-tarif.md', num: '2', label: 'Target Sasaran & Tarif Berdimensi', role: 'Admin Bendahara Lembaga' },
  { id: 'bab-3', file: '03-keringanan-dan-assignment-siswa.md', num: '3', label: 'Keringanan & Assignment Siswa', role: 'Admin Bendahara Lembaga' },
  { id: 'bab-4', file: '04-monitoring-jenis-tagihan.md', num: '4', label: 'Monitoring Jenis Tagihan', role: 'Admin Bendahara Lembaga' },
  { id: 'bab-5', file: '05-tagihan-perlu-ditinjau.md', num: '5', label: 'Tagihan Perlu Ditinjau', role: 'Admin Bendahara Lembaga' },
  { id: 'bab-6', file: '06-virtual-account-dan-verifikasi-manual.md', num: '6', label: 'Virtual Account & Verifikasi Manual', role: 'Admin Bendahara Lembaga' },
  { id: 'bab-7', file: '07-portal-ortu-dompet-dan-pembayaran.md', num: '7', label: 'Portal Ortu — Dompet & Pembayaran', role: 'Orang Tua / Wali Siswa' },
  { id: 'bab-8', file: '08-portal-ortu-tagihan-dan-riwayat.md', num: '8', label: 'Portal Ortu — Tagihan & Riwayat', role: 'Orang Tua / Wali Siswa' },
  { id: 'lampiran', file: 'lampiran-notifikasi-lintas-role.md', num: 'L', label: 'Notifikasi & Badge Lintas Role', role: 'Semua Role' },
];

const BR_TOKEN = ' BR ';

function imgDataUri(file) {
  const p = path.join(IMG_DIR, file);
  if (!fs.existsSync(p)) {
    console.warn(`Warning: Image ${file} not found at ${p}`);
    return '';
  }
  const buf = fs.readFileSync(p);
  return `data:image/png;base64,${buf.toString('base64')}`;
}

function escapeHtml(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

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

  function gatherContinuation(start) {
    let j = start;
    const parts = [];
    while (j < lines.length) {
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
      html += `2<h2>${inline(line.replace(/^## /, ''))}</h2>\n`;
    } else if (/^# /.test(line)) {
      // top H1 handled by container
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
      } else if (inOl && listContinuesAfterBlank(i + 1, /^\d+\. /)) {
      } else {
        closeLists();
      }
    } else if (/^!\[[^\]]*\]\([^)]+\)\s*$/.test(line.trim())) {
      closeLists();
      html += `${inline(line.trim())}\n`;
    } else {
      closeLists();
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
  // fix H2 replacement artefact
  html = html.replace(/2<h2>/g, '<h2>');
  return html;
}

function wrapSection(html, heading, cls) {
  const re = new RegExp(`<h2>${heading}</h2>\\n([\\s\\S]*?)(?=<h2>|$)`);
  return html.replace(re, (m, inner) => `<h2>${heading}</h2>\n<div class="${cls}">\n${inner}</div>\n`);
}

const chapterHtmlParts = CHAPTERS.map((ch) => {
  const fileP = path.join(ROOT, ch.file);
  if (!fs.existsSync(fileP)) {
    return `<section class="chapter" id="${ch.id}"><h1>${ch.label} (Belum ditulis)</h1></section>`;
  }
  const raw = fs.readFileSync(fileP, 'utf8');
  const body = raw.replace(/^# .+\n/, '');
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
  .replace('<title>Manual Book — Modul Akademik · Yayasan Permata</title>', '<title>Manual Book — Modul Keuangan · Pintera</title>')
  .replace('<h1>Modul Akademik — Yayasan Permata</h1>', '<h1>Modul Keuangan — Pintera</h1>')
  .replace('Panduan penggunaan modul akademik Pintera', 'Panduan penggunaan modul Keuangan Pintera')
  .replace('8 bab', '9 bab &amp; 1 lampiran')
  .replace('<!--NAV-->', navHtml)
  .replace('<!--CHAPTERS-->', chapterHtmlParts);

fs.mkdirSync(OUT_DIR, { recursive: true });
fs.writeFileSync(OUT, finalHtml, 'utf8');
console.log('wrote', OUT, (fs.statSync(OUT).size / 1024 / 1024).toFixed(2), 'MB');

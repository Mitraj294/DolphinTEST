#!/usr/bin/env node
// Simple dry-run scanner for JavaScript and Vue files that contain comment tokens.
// Usage: node find_js_vue_comments.js --path path/to/dir

const fs = require('node:fs');
const path = require('node:path');

function walk(dir, extList, fileList = []) {
  const files = fs.readdirSync(dir);
  for (const file of files) {
    const full = path.join(dir, file);
    const stat = fs.statSync(full);
    if (stat.isDirectory()) {
      if (file === 'node_modules' || file === '.git') continue;
      walk(full, extList, fileList);
    } else {
      const ext = path.extname(full).toLowerCase();
      if (extList.includes(ext)) fileList.push(full);
    }
  }
  return fileList;
}

const args = process.argv.slice(2);
const pIndex = args.indexOf('--path');
const base = pIndex >= 0 && args[pIndex+1] ? args[pIndex+1] : 'Dolphin_Frontend/src';

const exts = ['.js', '.ts', '.vue', '.jsx', '.tsx'];
let files = [];
try {
  files = walk(base, exts);
} catch (e) {
  console.error('Error reading path', base, e.message);
  process.exit(2);
}

const matches = [];
for (const f of files) {
  try {
    const content = fs.readFileSync(f, 'utf8');
    if (/\/\//.test(content) || /\/\*[\s\S]*?\*\//.test(content) || /<!--/.test(content)) {
      matches.push(f);
    }
  } catch (e) {
    // Warn when a file cannot be read so the scan output is actionable
    console.warn('Failed to read file', f, e && e.message ? e.message : e);
  }
}

if (matches.length === 0) {
  console.log('No frontend files with comment tokens found under', base);
  process.exit(0);
}

console.log('Frontend files that contain comment tokens (dry-run):');
for (const m of matches) console.log(' -', m);
console.log('\nTotal:', matches.length);
process.exit(0);

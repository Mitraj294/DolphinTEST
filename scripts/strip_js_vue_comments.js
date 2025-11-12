#!/usr/bin/env node
// Conservative JS/Vue comment stripper.
// - For .js/.ts files: removes // and /* */ comments, preserving strings.
// - For .vue files: only processes the contents of <script>...</script> blocks (including <script setup>).
// Usage: node strip_js_vue_comments.js --files file1,file2,... [--apply]

const fs = require('fs');

function stripCommentsFromJs(src) {
  let out = '';
  let i = 0;
  const len = src.length;
  let state = 'normal'; // normal, single_quote, double_quote, template, regex, line_comment, block_comment
  while (i < len) {
    const ch = src[i];
    const ch2 = src[i+1] || '';
    if (state === 'normal') {
      if (ch === '/' && ch2 === '/') {
        state = 'line_comment';
        i += 2;
        continue;
      }
      if (ch === '/' && ch2 === '*') {
        state = 'block_comment';
        i += 2;
        continue;
      }
      if (ch === '"') { out += ch; state = 'double_quote'; i++; continue; }
      if (ch === "'") { out += ch; state = 'single_quote'; i++; continue; }
      if (ch === '`') { out += ch; state = 'template'; i++; continue; }
      // naive regex start detection (only if previous non-space is one of these)
      // to avoid false positives, we skip detecting regex and just treat / as normal
      out += ch; i++; continue;
    }
    if (state === 'line_comment') {
      if (ch === '\n') { out += ch; state = 'normal'; }
      i++; continue;
    }
    if (state === 'block_comment') {
      if (ch === '*' && ch2 === '/') { state = 'normal'; i += 2; continue; }
      i++; continue;
    }
    if (state === 'double_quote') {
      if (ch === '\\') { out += ch; out += src[i+1] || ''; i += 2; continue; }
      if (ch === '"') { out += ch; state = 'normal'; i++; continue; }
      out += ch; i++; continue;
    }
    if (state === 'single_quote') {
      if (ch === '\\') { out += ch; out += src[i+1] || ''; i += 2; continue; }
      if (ch === "'") { out += ch; state = 'normal'; i++; continue; }
      out += ch; i++; continue;
    }
    if (state === 'template') {
      if (ch === '\\') { out += ch; out += src[i+1] || ''; i += 2; continue; }
      if (ch === '`') { out += ch; state = 'normal'; i++; continue; }
      out += ch; i++; continue;
    }
  }
  // collapse 3+ newlines to 2
  return out.replace(/\n{3,}/g, '\n\n');
}

function processVueFile(content) {
  // find all <script ...>...</script> (non-greedy)
  return content.replace(/<script([\s\S]*?)>([\s\S]*?)<\/script>/gi, (m, attrs, inner) => {
    const stripped = stripCommentsFromJs(inner);
    return `<script${attrs}>${stripped}</script>`;
  });
}

function main() {
  const args = process.argv.slice(2);
  const filesArgIndex = args.indexOf('--files');
  const apply = args.includes('--apply');
  if (filesArgIndex === -1 || !args[filesArgIndex+1]) {
    console.error('Usage: node strip_js_vue_comments.js --files file1,file2,... [--apply]');
    process.exit(2);
  }
  const files = args[filesArgIndex+1].split(',').map(s => s.trim()).filter(Boolean);
  const changed = [];
  for (const f of files) {
    if (!fs.existsSync(f)) { console.warn('Not found', f); continue; }
    const src = fs.readFileSync(f, 'utf8');
    let out = src;
    if (f.endsWith('.vue')) {
      out = processVueFile(src);
    } else if (f.endsWith('.js') || f.endsWith('.ts')) {
      out = stripCommentsFromJs(src);
    } else {
      continue;
    }
    if (out !== src) {
      changed.push(f);
      if (apply) fs.writeFileSync(f, out, 'utf8');
    }
  }
  if (changed.length === 0) {
    console.log('No files changed.');
    return;
  }
  console.log((apply ? 'Modified:' : 'Would modify:'));
  for (const c of changed) console.log(' -', c);
}

main();

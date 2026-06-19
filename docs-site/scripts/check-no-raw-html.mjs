import { readFile, readdir } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../docs/', import.meta.url));
const allowed = new Set(['README.md']);
const htmlPattern = /<\/?[a-z][\w:-]*(?:\s[^>]*)?>/i;

async function walk(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...await walk(path));
    } else if (entry.name.endsWith('.md')) {
      files.push(path);
    }
  }
  return files;
}

const files = await walk(root);
const failures = [];

for (const file of files) {
  if (allowed.has(file.split(/[\\/]/).pop())) continue;
  const text = await readFile(file, 'utf8');
  if (htmlPattern.test(text)) {
    failures.push(file);
  }
}

if (failures.length > 0) {
  console.error('Raw HTML is not allowed in docs Markdown:');
  for (const file of failures) console.error(`- ${file}`);
  process.exit(1);
}

console.log(`Markdown raw HTML guard passed for ${files.length} files.`);

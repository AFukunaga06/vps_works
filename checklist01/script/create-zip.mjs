import { createWriteStream, readFileSync, readdirSync, statSync } from 'fs';
import { join, relative } from 'path';
import { deflateRawSync } from 'zlib';

// Minimal ZIP file creator using Node.js built-ins
function crc32(buf) {
  const table = new Uint32Array(256);
  for (let i = 0; i < 256; i++) {
    let c = i;
    for (let j = 0; j < 8; j++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
    table[i] = c;
  }
  let crc = 0xFFFFFFFF;
  for (const byte of buf) crc = table[(crc ^ byte) & 0xFF] ^ (crc >>> 8);
  return (crc ^ 0xFFFFFFFF) >>> 0;
}

function dosDateTime(date) {
  const d = date || new Date();
  const dosDate = ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate();
  const dosTime = (d.getHours() << 11) | (d.getMinutes() << 5) | Math.floor(d.getSeconds() / 2);
  return { dosDate, dosTime };
}

function writeUint16LE(buf, offset, val) { buf[offset] = val & 0xFF; buf[offset+1] = (val >> 8) & 0xFF; }
function writeUint32LE(buf, offset, val) {
  buf[offset] = val & 0xFF; buf[offset+1] = (val>>8)&0xFF;
  buf[offset+2] = (val>>16)&0xFF; buf[offset+3] = (val>>24)&0xFF;
}

const root = '/home/runner/workspace';
const excludeDirs = new Set(['.git', 'node_modules', '.cache', '.local', '.upm', 'attached_assets']);
const excludeFiles = new Set(['checklist-app.zip', 'checklist-app.tar.gz']);

function collectFiles(dir, baseDir) {
  const results = [];
  for (const entry of readdirSync(dir)) {
    if (excludeDirs.has(entry) || excludeFiles.has(entry)) continue;
    const fullPath = join(dir, entry);
    const stat = statSync(fullPath);
    if (stat.isDirectory()) {
      results.push(...collectFiles(fullPath, baseDir));
    } else {
      results.push({ fullPath, arcName: relative(baseDir, fullPath) });
    }
  }
  return results;
}

const files = collectFiles(root, root);
const chunks = [];
const centralDir = [];
let offset = 0;

for (const { fullPath, arcName } of files) {
  const data = readFileSync(fullPath);
  const compressed = deflateRawSync(data, { level: 6 });
  const crc = crc32(data);
  const { dosDate, dosTime } = dosDateTime();
  const nameBytes = Buffer.from(arcName, 'utf8');

  // Local file header
  const localHeader = Buffer.alloc(30 + nameBytes.length);
  writeUint32LE(localHeader, 0, 0x04034b50);  // signature
  writeUint16LE(localHeader, 4, 20);           // version needed
  writeUint16LE(localHeader, 6, 0);            // flags
  writeUint16LE(localHeader, 8, 8);            // deflate
  writeUint16LE(localHeader, 10, dosTime);
  writeUint16LE(localHeader, 12, dosDate);
  writeUint32LE(localHeader, 14, crc);
  writeUint32LE(localHeader, 18, compressed.length);
  writeUint32LE(localHeader, 22, data.length);
  writeUint16LE(localHeader, 26, nameBytes.length);
  writeUint16LE(localHeader, 28, 0);
  nameBytes.copy(localHeader, 30);

  chunks.push(localHeader, compressed);

  // Central directory entry
  const cdEntry = Buffer.alloc(46 + nameBytes.length);
  writeUint32LE(cdEntry, 0, 0x02014b50);  // signature
  writeUint16LE(cdEntry, 4, 20);           // version made by
  writeUint16LE(cdEntry, 6, 20);           // version needed
  writeUint16LE(cdEntry, 8, 0);            // flags
  writeUint16LE(cdEntry, 10, 8);           // deflate
  writeUint16LE(cdEntry, 12, dosTime);
  writeUint16LE(cdEntry, 14, dosDate);
  writeUint32LE(cdEntry, 16, crc);
  writeUint32LE(cdEntry, 20, compressed.length);
  writeUint32LE(cdEntry, 24, data.length);
  writeUint16LE(cdEntry, 28, nameBytes.length);
  writeUint16LE(cdEntry, 30, 0);           // extra len
  writeUint16LE(cdEntry, 32, 0);           // comment len
  writeUint16LE(cdEntry, 34, 0);           // disk start
  writeUint16LE(cdEntry, 36, 0);           // int attr
  writeUint32LE(cdEntry, 38, 0);           // ext attr
  writeUint32LE(cdEntry, 42, offset);      // local header offset
  nameBytes.copy(cdEntry, 46);
  centralDir.push(cdEntry);

  offset += localHeader.length + compressed.length;
}

const cdBuffer = Buffer.concat(centralDir);
const eocd = Buffer.alloc(22);
writeUint32LE(eocd, 0, 0x06054b50);
writeUint16LE(eocd, 4, 0);
writeUint16LE(eocd, 6, 0);
writeUint16LE(eocd, 8, files.length);
writeUint16LE(eocd, 10, files.length);
writeUint32LE(eocd, 12, cdBuffer.length);
writeUint32LE(eocd, 16, offset);
writeUint16LE(eocd, 20, 0);

const zipBuffer = Buffer.concat([...chunks, cdBuffer, eocd]);
import { writeFileSync } from 'fs';
writeFileSync('/home/runner/workspace/checklist-app.zip', zipBuffer);
console.log(`Created checklist-app.zip (${(zipBuffer.length / 1024).toFixed(1)} KB, ${files.length} files)`);

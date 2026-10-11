import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { dirname, resolve, extname, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const types = { '.html': 'text/html; charset=utf-8', '.jpg': 'image/jpeg', '.png': 'image/png', '.woff2': 'font/woff2' };
const server = createServer(async (request, response) => {
    const path = resolve(root, '.' + decodeURIComponent(new URL(request.url, 'http://127.0.0.1').pathname));
    if (!path.startsWith(root + sep)) {
        response.writeHead(403).end();
        return;
    }
    try {
        const content = await readFile(path);
        response.writeHead(200, { 'Content-Type': types[extname(path)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
        response.end(content);
    } catch {
        response.writeHead(404).end();
    }
});
server.listen(4175, '127.0.0.1', () => console.log('Showcase preview ready on local port 4175.'));
process.stdin.on('data', (chunk) => {
    if (chunk.toString().includes('\u0003')) {
        server.close(() => process.exit(0));
    }
});

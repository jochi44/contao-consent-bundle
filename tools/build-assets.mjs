// Builds the minified assets the bundle ships next to its readable sources:
// public/juhe-consent.min.js, public/juhe-consent.min.css and public/manifest.json.
// Contao versions bundle asset URLs through public/manifest.json, so asset('juhe-consent.js',
// 'juhe_consent') serves the minified file with a content hash, and a changed file gets a new
// URL despite the long cache lifetime of /bundles.
//
//   npm ci && node build-assets.mjs           write the files
//   node build-assets.mjs --check             exit 1 when a committed file is stale
//
// Each minified file starts with the SHA-256 of its source, which the PHPUnit freshness test
// compares without Node.

import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { transform } from 'lightningcss';
import { minify } from 'terser';

const pub = join(dirname(fileURLToPath(import.meta.url)), '..', 'public');
const sha = (text) => createHash('sha256').update(text).digest('hex');
const banner = (name, source) => `/*! ${name} sha256:${sha(source)} */\n`;

const js = readFileSync(join(pub, 'juhe-consent.js'), 'utf8');
const css = readFileSync(join(pub, 'juhe-consent.css'), 'utf8');

const minJs = (await minify(js, { ecma: 2020, compress: { passes: 2 }, mangle: true, format: { comments: false } })).code;
const minCss = transform({ filename: 'juhe-consent.css', code: Buffer.from(css), minify: true }).code.toString();

const files = {
    'juhe-consent.min.js': `${banner('juhe-consent.js', js)}${minJs}\n`,
    'juhe-consent.min.css': `${banner('juhe-consent.css', css)}${minCss}\n`,
};
files['manifest.json'] = `${JSON.stringify(
    {
        'juhe-consent.js': `juhe-consent.min.js?v=${sha(files['juhe-consent.min.js']).slice(0, 10)}`,
        'juhe-consent.css': `juhe-consent.min.css?v=${sha(files['juhe-consent.min.css']).slice(0, 10)}`,
    },
    null,
    4,
)}\n`;

const check = process.argv.includes('--check');
let stale = 0;

for (const [name, content] of Object.entries(files)) {
    const path = join(pub, name);
    let current = null;

    try {
        current = readFileSync(path, 'utf8');
    } catch {
        // missing counts as stale
    }

    if (current === content) {
        continue;
    }

    if (check) {
        console.error(`stale: public/${name}`);
        stale++;
    } else {
        writeFileSync(path, content);
        console.log(`wrote public/${name} (${Buffer.byteLength(content)} bytes)`);
    }
}

if (check && stale > 0) {
    console.error('Run: cd tools && npm ci && node build-assets.mjs');
    process.exit(1);
}

console.log(check ? 'assets are current' : 'done');

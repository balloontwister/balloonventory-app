import JsBarcode from 'jsbarcode';

// Coordinate scale for label SVGs: user units per inch.
const UNITS_PER_INCH = 100;

const FONT_FAMILY = 'ui-sans-serif, system-ui, Arial, sans-serif';
const MONO_FAMILY = 'ui-monospace, SFMono-Regular, Menlo, monospace';

// Single-label size presets (inches). Custom dimensions are also supported.
export const LABEL_PRESETS = [
    { key: 'avery5160', label: 'Avery 5160 — 2⅝ × 1 in', widthIn: 2.625, heightIn: 1 },
    { key: 'dymo30252', label: 'Dymo 30252 — 3½ × 1⅛ in', widthIn: 3.5, heightIn: 1.125 },
    { key: 'dymo30336', label: 'Dymo 30336 — 2⅛ × 1 in', widthIn: 2.125, heightIn: 1 },
    { key: 'square2', label: 'Square — 2 × 2 in', widthIn: 2, heightIn: 2 },
    { key: 'small', label: 'Small — 1½ × ¾ in', widthIn: 1.5, heightIn: 0.75 },
];

// Avery (US Letter) sheet formats for bulk printing. Dimensions in inches.
export const AVERY_FORMATS = [
    {
        key: 'avery5160',
        label: 'Avery 5160 — 30 per sheet (2⅝ × 1 in)',
        cols: 3,
        rows: 10,
        labelWidthIn: 2.625,
        labelHeightIn: 1,
        marginTopIn: 0.5,
        marginLeftIn: 0.1875,
        colGapIn: 0.125,
        rowGapIn: 0,
    },
    {
        key: 'avery5167',
        label: 'Avery 5167 — 80 per sheet (1¾ × ½ in)',
        cols: 4,
        rows: 20,
        labelWidthIn: 1.75,
        labelHeightIn: 0.5,
        marginTopIn: 0.5,
        marginLeftIn: 0.3,
        colGapIn: 0.3,
        rowGapIn: 0,
    },
    {
        key: 'avery5163',
        label: 'Avery 5163 — 10 per sheet (4 × 2 in)',
        cols: 2,
        rows: 5,
        labelWidthIn: 4,
        labelHeightIn: 2,
        marginTopIn: 0.5,
        marginLeftIn: 0.15625,
        colGapIn: 0.1875,
        rowGapIn: 0,
    },
];

function escapeXml(value) {
    return String(value ?? '').replace(
        /[&<>"']/g,
        (c) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[c],
    );
}

// Shrink a font size (in user units == px here) until the text fits maxWidth.
function fitFontSize(text, maxWidth, startSize, weight, family) {
    const ctx = document.createElement('canvas').getContext('2d');
    let size = startSize;
    ctx.font = `${weight} ${size}px ${family}`;
    while (size > 6 && ctx.measureText(text).width > maxWidth) {
        size -= 1;
        ctx.font = `${weight} ${size}px ${family}`;
    }
    return size;
}

/**
 * Build a self-contained SVG string for one bin label, sized to the given
 * physical dimensions. Layout: name on top, barcode in the middle, the
 * scan_code beneath. Used for the on-screen preview and every export.
 */
export function buildLabelSvg({ name, code, widthIn, heightIn }) {
    // Render the Code 128 bars into a throwaway SVG, then nest them.
    const bc = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    JsBarcode(bc, code, {
        format: 'CODE128',
        displayValue: false,
        height: 100,
        width: 2,
        margin: 0,
        background: 'transparent',
    });
    const bcW = parseFloat(bc.getAttribute('width')) || 100;
    const bcH = parseFloat(bc.getAttribute('height')) || 100;
    const bars = bc.innerHTML;

    const W = widthIn * UNITS_PER_INCH;
    const H = heightIn * UNITS_PER_INCH;
    const pad = Math.max(W, H) * 0.05;
    const inner = W - pad * 2;

    const nameSize = fitFontSize(name, inner, H * 0.2, '600', FONT_FAMILY);
    const codeSize = fitFontSize(code, inner, H * 0.13, '400', MONO_FAMILY);

    const nameBaseline = pad + nameSize;
    const codeBaseline = H - pad;
    const barTop = nameBaseline + H * 0.04;
    const barBottom = codeBaseline - codeSize - H * 0.04;
    const barHeight = Math.max(barBottom - barTop, H * 0.2);

    return `<svg xmlns="http://www.w3.org/2000/svg" width="${widthIn}in" height="${heightIn}in" viewBox="0 0 ${W} ${H}">
  <rect x="0" y="0" width="${W}" height="${H}" fill="#ffffff"/>
  <text x="${W / 2}" y="${nameBaseline}" text-anchor="middle" font-family="${FONT_FAMILY}" font-weight="600" font-size="${nameSize}" fill="#111111">${escapeXml(name)}</text>
  <svg x="${pad}" y="${barTop}" width="${inner}" height="${barHeight}" viewBox="0 0 ${bcW} ${bcH}" preserveAspectRatio="none">${bars}</svg>
  <text x="${W / 2}" y="${codeBaseline}" text-anchor="middle" font-family="${MONO_FAMILY}" font-size="${codeSize}" fill="#333333">${escapeXml(code)}</text>
</svg>`;
}

/**
 * Rasterize a label SVG to a PNG Blob at the given DPI (default 300 for crisp
 * print/scan). Returns a Promise<Blob>.
 */
export function labelToPngBlob(svgString, widthIn, heightIn, dpi = 300) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(widthIn * dpi);
            canvas.height = Math.round(heightIn * dpi);
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(
                (blob) => (blob ? resolve(blob) : reject(new Error('toBlob failed'))),
                'image/png',
            );
        };
        img.onerror = () => reject(new Error('SVG load failed'));
        img.src =
            'data:image/svg+xml;base64,' +
            btoa(unescape(encodeURIComponent(svgString)));
    });
}

/**
 * Build a printable HTML document laying labels out on an Avery sheet grid.
 */
export function buildAverySheetHtml(labels, format, title) {
    const perSheet = format.cols * format.rows;
    const cells = labels
        .map((label, i) => {
            const slot = i % perSheet;
            const col = slot % format.cols;
            const row = Math.floor(slot / format.cols);
            const left =
                format.marginLeftIn + col * (format.labelWidthIn + format.colGapIn);
            const top =
                format.marginTopIn + row * (format.labelHeightIn + format.rowGapIn);
            const breakBefore = i > 0 && slot === 0 ? 'page-break-before: always;' : '';
            return `<div class="cell" style="left:${left}in; top:${top}in; width:${format.labelWidthIn}in; height:${format.labelHeightIn}in; ${breakBefore}">${buildLabelSvg(
                {
                    name: label.name,
                    code: label.code,
                    widthIn: format.labelWidthIn,
                    heightIn: format.labelHeightIn,
                },
            )}</div>`;
        })
        .join('');

    return `<!doctype html><html><head><meta charset="utf-8"><title>${escapeXml(title)}</title>
<style>
  @page { size: letter; margin: 0; }
  html, body { margin: 0; padding: 0; }
  .cell { position: absolute; overflow: hidden; }
  .cell svg { width: 100%; height: 100%; display: block; }
</style></head><body>${cells}</body></html>`;
}

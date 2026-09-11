/* global figma, __html__ */
/**
 * Construieste cadrele de prezentare (RO + RU) pentru produse, identic cu
 * cadrele existente din fisierul "Toate pozele jpg":
 *
 *   Frame 1000x1000, fundal alb, clip
 *     - dreptunghi 1000x1000 cu fundalul generat (image fill, FILL)
 *     - randul 1: intrebarea   - Figtree Regular 43, #302f2f, glow alb
 *     - randul 2: verbul       - Figtree Black 102, gradient din culorile ambalajului, umbra alba
 *     - randul 3: substantivul - Figtree ExtraBold 83, #302f2f, umbra alba
 *
 * Cadrele RO merg pe randul de sus (y=358), cele RU pe randul de jos (y=1469),
 * o coloana noua pentru fiecare produs, la dreapta a tot ce exista pe pagina.
 *
 * Primeste de la ui.html cate un produs pe rand ({type:'build-item'}) si
 * raspunde cu id-urile cadrelor create, ca sa poata fi exportate apoi de
 * upload_to_wp.php.
 */

var FONT = 'Figtree';
var ROW_RO = 358;
var ROW_RU = 1469;
var COLUMN = 1200;
var TEXT_X = 41;
var TEXT_MAX_W = 920;
var LINE_HEIGHT = { unit: 'PERCENT', value: 110.6873869895935 };
var GRADIENT_TF = [
  [0.6656328439712524, 0.4650788903236389, -0.033503130078315735],
  [-18.726951599121094, 0.8767051100730896, 7.788607120513916]
];
var DARK = { r: 0x30 / 255, g: 0x2f / 255, b: 0x2f / 255 };
var GLOW = [{ type: 'DROP_SHADOW', color: { r: 1, g: 1, b: 1, a: 0.55 }, offset: { x: 0, y: 0 }, radius: 21.9, spread: 0, visible: true, blendMode: 'NORMAL' }];
var SHADOW = [{ type: 'DROP_SHADOW', color: { r: 1, g: 1, b: 1, a: 0.25 }, offset: { x: 0, y: 4 }, radius: 4, spread: 0, visible: true, blendMode: 'NORMAL' }];

function hexToRgb(hex) {
  var h = hex.replace('#', '');
  return { r: parseInt(h.substr(0, 2), 16) / 255, g: parseInt(h.substr(2, 2), 16) / 255, b: parseInt(h.substr(4, 2), 16) / 255 };
}

function luminance(hex) {
  var c = hexToRgb(hex);
  return 0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b;
}

function darken(hex, f) {
  var h = hex.replace('#', ''), out = '';
  for (var i = 0; i < 6; i += 2) {
    out += ('0' + Math.floor(parseInt(h.substr(i, 2), 16) * f).toString(16)).slice(-2);
  }
  return out;
}

/* Gradientele deschise (galben, pastel) nu se citesc pe fundal deschis. */
function gradientPaint(start, end) {
  if (luminance(end) > 0.45 || luminance(start) > 0.7) {
    start = darken(start, 0.8);
    end = darken(end, 0.55);
  }
  var a = hexToRgb(start), b = hexToRgb(end);
  return {
    type: 'GRADIENT_LINEAR',
    gradientTransform: GRADIENT_TF,
    gradientStops: [
      { color: { r: a.r, g: a.g, b: a.b, a: 1 }, position: 0 },
      { color: { r: b.r, g: b.g, b: b.b, a: 1 }, position: 1 }
    ]
  };
}

function makeText(chars, style, size, fills, effects, lineHeight) {
  var t = figma.createText();
  t.fontName = { family: FONT, style: style };
  t.characters = chars;
  t.fontSize = size;
  if (lineHeight) t.lineHeight = lineHeight;
  t.textCase = 'UPPER';
  t.textAutoResize = 'WIDTH_AND_HEIGHT';
  t.textAlignHorizontal = 'LEFT';
  t.fills = fills;
  t.effects = effects;
  t.name = chars;
  return t;
}

/* Verbul si substantivul stau sub barbie (opts.textY, masurat de manifest.py)
   si raman la marimea originala; se micsoreaza doar daca nu incap in cadru.
   Intrebarea e la nivelul fetei si se limiteaza la zona libera (opts.textMaxW). */
function fitPair(v, n, maxW) {
  var s = Math.min(1, maxW / v.width, maxW / n.width);
  if (s < 1) {
    v.fontSize = Math.round(102 * s);
    n.fontSize = Math.round(83 * s);
  }
}

function fitOne(t, maxW) {
  while (t.width > maxW && t.fontSize > 28) {
    t.fontSize = t.fontSize - 1;
  }
}

function buildFrame(opts) {
  var frame = figma.createFrame();
  frame.name = opts.name;
  frame.resize(1000, 1000);
  frame.x = opts.x;
  frame.y = opts.y;
  frame.clipsContent = true;
  frame.fills = [{ type: 'SOLID', color: { r: 1, g: 1, b: 1 } }];

  var bg = figma.createRectangle();
  bg.name = 'Fundal';
  bg.resize(1000, 1000);
  bg.fills = [{ type: 'IMAGE', scaleMode: 'FILL', imageHash: opts.imageHash }];
  frame.appendChild(bg);
  bg.x = 0;
  bg.y = 0;

  var maxW = opts.textMaxW || TEXT_MAX_W;
  var q = makeText(opts.lines[0], 'Regular', 43, [{ type: 'SOLID', color: DARK }], GLOW, null);
  frame.appendChild(q);
  fitOne(q, maxW);
  q.x = TEXT_X; q.y = opts.textY;

  var v = makeText(opts.lines[1], 'Black', 102, [gradientPaint(opts.gradient[0], opts.gradient[1])], SHADOW, LINE_HEIGHT);
  frame.appendChild(v);
  var n = makeText(opts.lines[2], 'ExtraBold', 83, [{ type: 'SOLID', color: DARK }], SHADOW, LINE_HEIGHT);
  frame.appendChild(n);
  fitPair(v, n, TEXT_MAX_W);
  v.x = TEXT_X; v.y = q.y + q.height;
  n.x = TEXT_X; n.y = v.y + v.height;

  return frame;
}

function nextColumnX() {
  var maxX = 0;
  var children = figma.currentPage.children;
  for (var i = 0; i < children.length; i++) {
    var c = children[i];
    if (c.x + c.width > maxX) maxX = c.x + c.width;
  }
  return maxX + 200;
}

async function buildItem(item, x) {
  var imageHash = figma.createImage(item.image).hash;
  var ro = buildFrame({ name: item.name_ro, imageHash: imageHash, lines: item.ro, gradient: item.gradient, textY: item.text_y, textMaxW: item.text_max_w, x: x, y: ROW_RO });
  var ru = buildFrame({ name: item.name_ru, imageHash: imageHash, lines: item.ru, gradient: item.gradient, textY: item.text_y, textMaxW: item.text_max_w, x: x, y: ROW_RU });
  return { ro: ro.id, ru: ru.id, frames: [ro, ru] };
}

figma.showUI(__html__, { width: 420, height: 520 });

var columnX = null;
var built = [];

figma.ui.onmessage = async function (msg) {
  if (msg.type === 'start') {
    await figma.loadFontAsync({ family: FONT, style: 'Regular' });
    await figma.loadFontAsync({ family: FONT, style: 'ExtraBold' });
    await figma.loadFontAsync({ family: FONT, style: 'Black' });
    columnX = nextColumnX();
    built = [];
    figma.ui.postMessage({ type: 'ready' });
    return;
  }

  if (msg.type === 'build-item') {
    try {
      var r = await buildItem(msg.item, columnX);
      columnX += COLUMN;
      built = built.concat(r.frames);
      figma.ui.postMessage({ type: 'item-done', slug: msg.item.slug, ro: r.ro, ru: r.ru });
    } catch (e) {
      figma.ui.postMessage({ type: 'item-failed', slug: msg.item.slug, error: String(e && e.message ? e.message : e) });
    }
    return;
  }

  if (msg.type === 'finish') {
    if (built.length) {
      figma.currentPage.selection = built;
      figma.viewport.scrollAndZoomIntoView(built);
    }
    return;
  }

  if (msg.type === 'close') {
    figma.closePlugin();
  }
};

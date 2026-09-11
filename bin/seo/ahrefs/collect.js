// Ahrefs Free Keyword Generator collector (runs in the page console / Claude-in-Chrome JS tool).
// 1. Open https://ahrefs.com/keyword-generator/?country=ro&input=test (country: ro | md), wait for results.
// 2. Paste this whole file, then: window.__runKw(LIST, 'ht_kw_<key>')  — results go to localStorage[key].
// 3. Export: window.__export('ht_kw_<key>', 0, 60, true) -> JSON string; save as data/<prefix>_partN.json
//    (prefix ro = Google RO, ro2 = extra RO seeds, rumd = RU seeds on Google MD, romd = RO seeds on Google MD).
// Limits seen on 2026-09-07: ~250 queries per session before Cloudflare asks for an interactive CAPTCHA;
// two tabs in parallel trigger it faster. Repeating an identical query returns nothing (client cache) -> timeout.
window.__resp = [];
const __of = window.fetch;
window.fetch = async function (u, o) {
  const r = await __of.apply(this, arguments);
  if (String(u).includes('stGetFreeKeywordIdeas')) { try { window.__resp.push(await r.clone().json()); } catch (e) { window.__resp.push('ERR ' + e); } }
  return r;
};
window.__ls = window['local' + 'Storage'];
window.__get = k => JSON.parse(window.__ls.getItem(k) || '{}');
window.__lab = x => x.replace('MoreThan', '>').replace('LessThan', '<').replace('OneHundred', '100').replace('OneThousand', '1k').replace('TenThousand', '10k').replace('HundredThousand', '100k');
window.__export = (key, from, to, compact) => {
  const s = window.__get(key); const o = {};
  for (const k of Object.keys(s).slice(from, to)) {
    const v = s[k]; if (v.error) { o[k] = { e: v.error }; continue; }
    let ideas = v.ideas.map(x => [x[0], window.__lab(x[1]), (x[2] || '').slice(0, 1)]);
    if (compact) { const hi = ideas.filter(x => x[1] !== '<100'); const lo = ideas.filter(x => x[1] === '<100').slice(0, 6); ideas = hi.concat(lo).map(x => x[2] === 'U' ? [x[0], x[1]] : x); }
    o[k] = { t: v.total, i: ideas, q: v.questions.slice(0, 6).map(x => x[0]) };
  }
  return JSON.stringify(o);
};
window.__runKw = async function (list, key) {
  const store = window.__get(key); const setv = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
  window.__finished = null;
  for (const kw of list) {
    if (store[kw] && !store[kw].error) continue;
    try {
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
      await new Promise(r => setTimeout(r, 300));
      const inp = document.querySelector('input[type=text], input:not([type])'); const n0 = window.__resp.length;
      inp.focus(); setv.call(inp, kw); inp.dispatchEvent(new Event('input', { bubbles: true }));
      await new Promise(r => setTimeout(r, 300));
      const btn = [...document.querySelectorAll('button')].find(b => b.innerText.trim() === 'Find keywords');
      if (!btn) throw new Error('no button'); btn.click();
      let t = 0; while (window.__resp.length === n0 && t < 40) { await new Promise(r => setTimeout(r, 500)); t++; }
      const r = window.__resp[window.__resp.length - 1];
      if (window.__resp.length === n0) store[kw] = { error: 'timeout' };
      else if (Array.isArray(r) && r[0] === 'Ok' && r[1] && r[1].allIdeas) {
        const a = r[1].allIdeas, q = r[1].questionIdeas || {};
        store[kw] = { total: a.total || 0, ideas: (a.results || []).map(x => [x.keyword, x.volumeLabel, x.difficultyLabel]), qtotal: q.total || 0, questions: (q.results || []).map(x => [x.keyword, x.volumeLabel]) };
      } else if (Array.isArray(r) && r[0] === 'Ok') store[kw] = { total: 0, ideas: [], qtotal: 0, questions: [] };
      else store[kw] = { error: JSON.stringify(r).slice(0, 200) };
    } catch (e) { store[kw] = { error: 'exc ' + e.message }; }
    window.__ls.setItem(key, JSON.stringify(store)); window.__progress = kw + ' ' + Object.keys(store).length;
    await new Promise(r => setTimeout(r, 1500));
  }
  window.__finished = key; return Object.keys(store).length;
};

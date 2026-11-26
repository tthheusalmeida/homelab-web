import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
const require = createRequire("process.env.HOMELAB_DESKTOP_DIR ? process.env.HOMELAB_DESKTOP_DIR + "/package.json" : "D:/homelab/desktop/package.json"");
export const { chromium } = require("playwright");
export const D = path.dirname(new URL(import.meta.url).pathname.replace(/^\//, ""));

export async function connect() {
  const browser = await chromium.connectOverCDP("http://127.0.0.1:9222");
  const page = browser.contexts()[0].pages()[0];
  const cdp = await browser.contexts()[0].newCDPSession(page);
  await cdp.send("Emulation.setDeviceMetricsOverride", { width: 1360, height: 820, deviceScaleFactor: 1, mobile: false });
  return { browser, page, cdp };
}

/* Cursor falso desenhado na página (a captura de CDP não inclui o cursor do sistema). */
export async function installCursor(page) {
  await page.evaluate(() => {
    if (document.getElementById("demo-cursor")) return;
    const c = document.createElement("div");
    c.id = "demo-cursor";
    c.style.cssText = "position:fixed;left:0;top:0;width:22px;height:22px;z-index:2147483647;pointer-events:none;transform:translate(-100px,-100px);filter:drop-shadow(0 2px 3px rgba(0,0,0,.45))";
    c.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 2l15 9-6.5 1.6L9.6 19z" fill="#fff" stroke="#111" stroke-width="1.4" stroke-linejoin="round"/></svg>';
    document.body.appendChild(c);
    window.__cur = (x, y) => { c.style.transform = `translate(${x}px,${y}px)`; };
  });
}

export class Pointer {
  constructor(page) { this.page = page; this.x = 680; this.y = 420; }
  async move(x, y, ms = 600) {
    const steps = Math.max(8, Math.round(ms / 16));
    const x0 = this.x, y0 = this.y;
    for (let i = 1; i <= steps; i++) {
      const t = i / steps, e = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
      const nx = x0 + (x - x0) * e, ny = y0 + (y - y0) * e;
      await this.page.mouse.move(nx, ny);
      await this.page.evaluate(([a, b]) => window.__cur && window.__cur(a, b), [nx, ny]);
      await this.page.waitForTimeout(ms / steps);
    }
    this.x = x; this.y = y;
  }
  async moveTo(sel, ms = 600) {
    const b = await this.page.locator(sel).first().boundingBox();
    await this.move(b.x + b.width / 2, b.y + b.height / 2, ms);
  }
  async click(sel, ms = 600) { await this.moveTo(sel, ms); await this.page.waitForTimeout(120); await this.page.mouse.down(); await this.page.waitForTimeout(70); await this.page.mouse.up(); }
}

/* Gravação: frames do screencast com a hora de cada um, montados depois com o ffmpeg. */
export async function startRec(cdp, dir) {
  fs.rmSync(dir, { recursive: true, force: true });
  fs.mkdirSync(dir, { recursive: true });
  const frames = [];
  cdp.on("Page.screencastFrame", async (f) => {
    const name = `f${String(frames.length).padStart(5, "0")}.jpg`;
    fs.writeFileSync(path.join(dir, name), Buffer.from(f.data, "base64"));
    frames.push({ name, t: f.metadata.timestamp });
    try { await cdp.send("Page.screencastFrameAck", { sessionId: f.sessionId }); } catch {}
  });
  await cdp.send("Page.startScreencast", { format: "jpeg", quality: 95, maxWidth: 1360, maxHeight: 820, everyNthFrame: 1 });
  return async () => {
    await cdp.send("Page.stopScreencast");
    let list = "";
    for (let i = 0; i < frames.length; i++) {
      const dur = i < frames.length - 1 ? Math.max(0.001, frames[i + 1].t - frames[i].t) : 0.1;
      list += `file '${frames[i].name}'\nduration ${dur.toFixed(4)}\n`;
    }
    list += `file '${frames[frames.length - 1].name}'\n`;
    fs.writeFileSync(path.join(dir, "list.txt"), list);
    return frames.length;
  };
}

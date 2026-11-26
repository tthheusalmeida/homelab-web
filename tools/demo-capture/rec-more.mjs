// uso: node rec-more.mjs <livros|consumo|buscar|transcricao|visual|backup> <dark|light>
import { connect, installCursor, Pointer, startRec, D } from "./lib.mjs";
const [name, theme] = process.argv.slice(2);
const { browser, page, cdp } = await connect();
await page.keyboard.press("Escape");
const home = () => page.getByText("Início", { exact: true }).first().click();
await home();
await installCursor(page);
await page.mouse.move(900, 300); await page.evaluate(() => window.__cur(900, 300));
await page.waitForTimeout(700);
const p = new Pointer(page); p.x = 900; p.y = 300;
const wait = (ms) => page.waitForTimeout(ms);
const pc = async (loc, ms = 700) => {
  const l = loc.first(); await l.scrollIntoViewIfNeeded();
  const b = await l.boundingBox();
  await p.move(b.x + b.width / 2, b.y + b.height / 2, ms);
  await wait(120); await page.mouse.down(); await wait(70); await page.mouse.up();
};
const side = (t) => page.getByText(t, { exact: true }).first();

const stop = await startRec(cdp, `${D}/frames-${name}`);
await wait(800);

if (name === "livros") {
  await pc(side("Livros")); await wait(1500);
  await pc(page.getByRole("link", { name: "Direito Administrativo" })); await wait(2600);
  await pc(page.getByText("Média, mediana e moda").first()); await wait(2200);
} else if (name === "consumo") {
  await pc(side("Consumo")); await wait(2400);
  await p.move(900, 560, 900); await wait(1200);
  await pc(page.getByRole("button", { name: "Gemini" })); await wait(1700);
  await pc(page.getByRole("button", { name: "Groq" })); await wait(1500);
  await pc(page.getByRole("button", { name: "Todos" })); await wait(1300);
} else if (name === "buscar") {
  await pc(side("Páginas")); await wait(900);
  await pc(page.getByText("Princípios da Administração Pública")); await wait(1600);
  await page.keyboard.down("Control"); await page.keyboard.press("f"); await page.keyboard.up("Control"); await wait(600);
  for (const ch of "interesse público") { await page.keyboard.type(ch); await wait(85); }
  await wait(1300);
  await page.keyboard.press("Enter"); await wait(1100);
  await page.keyboard.press("Enter"); await wait(1200);
  await page.keyboard.press("Escape"); await wait(700);
} else if (name === "transcricao") {
  await pc(side("Páginas")); await wait(900);
  await pc(page.getByText("Aula 12: Atos administrativos")); await wait(2000);
  await p.move(1000, 520, 700);
  for (let i = 0; i < 7; i++) { await page.mouse.wheel(0, 170); await wait(480); }
  await wait(900);
  for (let i = 0; i < 7; i++) { await page.mouse.wheel(0, -170); await wait(200); }
  await wait(700);
} else if (name === "visual") {
  await pc(side("Configurações")); await wait(1500);
  await pc(page.getByRole("radio", { name: "Claro" }).or(page.getByText("Claro", { exact: true }))); await wait(1500);
  for (const c of ["Laranja", "Verde", "Violeta"]) { await pc(page.getByRole("radio", { name: c }).first(), 550); await wait(900); }
  await pc(page.getByRole("radio", { name: "Escuro" }).or(page.getByText("Escuro", { exact: true }))); await wait(1500);
  await pc(page.getByRole("radio", { name: "Ciano" }).first(), 550); await wait(1100);
} else if (name === "backup") {
  await pc(side("Configurações")); await wait(1100);
  await pc(page.getByText("Backup", { exact: true })); await wait(2000);
  await pc(page.getByText("Consumo de IA", { exact: true }), 800); await wait(1400);
  await pc(page.getByText("Mapas mentais", { exact: true }), 600); await wait(1400);
  await pc(page.getByText("Consumo de IA", { exact: true }), 600); await wait(1200);
} else throw new Error("clipe desconhecido: " + name);

await home(); await wait(1000);
console.log("frames", await stop());
await browser.close();

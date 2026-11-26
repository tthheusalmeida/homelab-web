// Servidor falso que imita o Ollama (/api/tags, /api/show, /api/chat) com respostas prontas para a gravação.
import http from "node:http";
import fs from "node:fs";

const port = +process.argv[2] || 11500;
const answers = JSON.parse(fs.readFileSync(new URL("./answers.json", import.meta.url), "utf8"));
const DELAY = +(process.env.MOCK_DELAY_MS || 0);
const log = (...a) => fs.appendFileSync(new URL("./mock.log", import.meta.url), a.join(" ") + "\n");

function route(system, user) {
  const all = (system + "\n" + user).toLowerCase();
  if (all.includes("mapa mental")) return answers.mindMap;
  if (all.includes("infográfico") || all.includes("infografico")) return answers.infographic || "{}";
  if (all.includes("classific") || all.includes("categoria")) return answers.classification;
  return answers.summary;
}

http.createServer((req, res) => {
  let body = "";
  req.on("data", (c) => (body += c));
  req.on("end", async () => {
    const json = (o) => { res.writeHead(200, { "Content-Type": "application/json" }); res.end(JSON.stringify(o)); };
    if (req.url === "/api/tags") return json({ models: [{ name: "llama3.1:8b", model: "llama3.1:8b", size: 4920000000, details: { parameter_size: "8B" } }] });
    if (req.url === "/api/show") return json({ model_info: { "llama.context_length": 131072 }, details: { parameter_size: "8B" } });
    if (req.url === "/api/chat") {
      const b = JSON.parse(body || "{}");
      const sys = (b.messages || []).filter((m) => m.role === "system").map((m) => m.content).join("\n");
      const usr = (b.messages || []).filter((m) => m.role !== "system").map((m) => m.content).join("\n");
      log("CHAT", "sys=" + JSON.stringify(sys.slice(0, 160)), "usr=" + JSON.stringify(usr.slice(0, 120)), "json=" + !!b.format);
      const content = route(sys, usr);
      if (DELAY) await new Promise((r) => setTimeout(r, DELAY));
      return json({ model: b.model, created_at: new Date().toISOString(), message: { role: "assistant", content }, done: true, prompt_eval_count: 900, eval_count: 400 });
    }
    log("OTHER", req.method, req.url);
    res.writeHead(404); res.end();
  });
}).listen(port, "127.0.0.1", () => console.log("mock ollama em", port));

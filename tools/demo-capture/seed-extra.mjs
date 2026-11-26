// Complementa a raiz de demonstração: transcrição com tempo, livros e consumo de IA. Rodar depois do seed.mjs.
import fs from "node:fs";
import path from "node:path";
import { randomUUID } from "node:crypto";

const root = process.argv[2];
const lib = path.join(root, "library");

/* ids das páginas já criadas, por título */
const ids = {};
(function walk(d) {
  for (const f of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, f.name);
    if (f.isDirectory()) walk(p);
    else if (f.name.endsWith(".md")) {
      const t = fs.readFileSync(p, "utf8");
      const id = /^id: "(.+)"$/m.exec(t)?.[1];
      const title = /^title: "(.+)"$/m.exec(t)?.[1];
      if (id && title) ids[title] = id;
    }
  }
})(lib);

/* transcrição com tempo */
const tid = randomUUID();
const blocks = [
  ["00:00:04", "Bom dia, pessoal. Hoje vamos falar de atos administrativos, um dos temas que mais caem em prova de direito administrativo."],
  ["00:02:31", "Primeiro, o conceito: ato administrativo é a manifestação de vontade da Administração que produz efeitos jurídicos imediatos, sob o regime de direito público."],
  ["00:06:48", "Os requisitos são cinco: competência, finalidade, forma, motivo e objeto. A sigla que ajuda a lembrar é COFIFOMOBJ, e vale decorar na ordem."],
  ["00:11:15", "Competência e finalidade são sempre vinculadas. Já motivo e objeto podem ser discricionários, e é nesse ponto que entra o mérito administrativo."],
  ["00:17:40", "Atenção para a diferença entre anulação e revogação. A anulação trata de ilegalidade e tem efeito retroativo; a revogação trata de conveniência e vale daqui para frente."],
  ["00:24:02", "A Administração pode anular os próprios atos, mas o prazo para anular atos que gerem efeitos favoráveis ao administrado é de cinco anos, salvo má-fé."],
  ["00:31:27", "Vamos aos atributos: presunção de legitimidade, imperatividade, autoexecutoriedade e tipicidade. Nem todo ato possui todos os atributos."],
  ["00:39:55", "Na prova, a banca costuma trocar o nome do atributo pela definição de outro. Releia sempre com calma antes de marcar."],
  ["00:47:10", "Para fechar, os principais exemplos: licença, autorização, permissão, admissão e aprovação. Cada um com uma natureza própria."],
].map(([t, x]) => `[${t}] ${x}`).join("\n\n");
const tdir = path.join(lib, "Concursos", "Administrativo");
fs.mkdirSync(tdir, { recursive: true });
fs.writeFileSync(path.join(tdir, `aula_12_atos_administrativos_${tid}.md`), [
  "---", `id: "${tid}"`, 'title: "Aula 12: Atos administrativos"', 'type: "transcription"', 'source: "video"',
  'sourceUrl: "https://www.youtube.com/watch?v=9bZkp7q19f0"', 'status: "published"', "category:", '  - "Concursos"', '  - "Administrativo"',
  "tags:", '  - "atos administrativos"', '  - "aula"', 'createdAt: "2026-11-25T19:00:00Z"', 'updatedAt: "2026-11-25T19:00:00Z"', "---", "", blocks, "",
].join("\n"));
ids["Aula 12: Atos administrativos"] = tid;

/* livros */
const bdir = path.join(lib, "_livros");
fs.mkdirSync(bdir, { recursive: true });
const now = "2026-11-25T12:00:00Z";
function book(title, subtitle, kind, color, mods, read, last) {
  const id = randomUUID();
  const docs = mods.flat().map((t) => ids[t]).filter(Boolean);
  fs.writeFileSync(path.join(bdir, `${id}.json`), JSON.stringify({
    id, title, kind, description: "", subtitle, coverColor: color, coverImageUrl: "",
    readDocumentIds: read.map((t) => ids[t]).filter(Boolean),
    lastPosition: last ? { documentId: ids[last], elementId: `chapter-${ids[last]}` } : null,
    completedAt: null, createdAt: now, updatedAt: now,
    modules: mods.map((m, i) => ({ id: randomUUID(), title: ["Fundamentos", "Aprofundamento", "Revisão"][i] || "", documentIds: m.map((t) => ids[t]).filter(Boolean) })),
  }, null, 2));
}
book("Direito Administrativo", "Preparação para concursos", "study", "blue",
  [["Princípios da Administração Pública", "Aula 12: Atos administrativos"], ["Média, mediana e moda"]],
  ["Princípios da Administração Pública"], "Aula 12: Atos administrativos");
book("Ciências da Natureza", "Ensino médio", "course", "emerald",
  [["Fotossíntese: etapas e equação", "Leis de Newton"], ["Revolução Francesa"]], ["Fotossíntese: etapas e equação", "Leis de Newton"], "Leis de Newton");
book("Infraestrutura e DevOps", "Pós-graduação", "pos", "violet",
  [["Containers x máquinas virtuais", "Modelo OSI em 7 camadas"], ["Listas e dicionários em Python"]], ["Containers x máquinas virtuais"], "Modelo OSI em 7 camadas");

/* consumo de IA: 14 dias */
const ops = ["summary", "summary", "mind-map", "infographic", "chat", "classification"];
const provs = [
  ["gemini", "gemini-2.5-flash", 0.0000003, 0.0000025],
  ["groq", "llama-3.3-70b-versatile", 0.00000059, 0.00000079],
  ["ollama", "llama3.1:8b", 0, 0],
];
const records = [];
let seed = 7; const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
for (let d = 13; d >= 0; d--) {
  const n = 2 + Math.floor(rnd() * 5);
  for (let k = 0; k < n; k++) {
    const [provider, model, cin, cout] = provs[Math.floor(rnd() * 3)];
    const inT = 1500 + Math.floor(rnd() * 14000), outT = 300 + Math.floor(rnd() * 2500);
    const at = new Date(Date.now() - d * 86400000 - Math.floor(rnd() * 40000000));
    records.push({
      id: randomUUID(), provider, model, operation: ops[Math.floor(rnd() * ops.length)],
      inputTokens: inT, outputTokens: outT, totalTokens: inT + outT, estimatedCost: +(inT * cin + outT * cout).toFixed(6),
      latencyMs: 900 + Math.floor(rnd() * 9000), success: rnd() > 0.04, createdAt: at.toISOString(),
    });
  }
}
records.sort((a, b) => b.createdAt.localeCompare(a.createdAt));
fs.writeFileSync(path.join(root, "data", "ai-usage.json"), JSON.stringify({ version: 1, records }));
console.log("extra ok", records.length, "registros");

// Cria a raiz de dados de demonstração (biblioteca, config) para gravar a landing.
import fs from "node:fs";
import path from "node:path";
import { randomUUID } from "node:crypto";

const root = process.argv[2];
const theme = process.argv[3] || "dark";
const mockPort = process.argv[4] || "11500";
fs.rmSync(root, { recursive: true, force: true });
for (const d of ["library", "data", "config", "inbox", "temp/video", "temp/audio", "temp/pdf"]) fs.mkdirSync(path.join(root, d), { recursive: true });

const pages = [
  ["Princípios da Administração Pública", ["Concursos", "Administrativo"], ["LIMPE", "princípios", "art. 37"], "2026-11-24T14:10:00Z",
`# Princípios da Administração Pública

## Os cinco princípios expressos (LIMPE)
- **Legalidade:** o administrador só faz o que a lei autoriza; o particular pode fazer tudo que a lei não proíbe.
- **Impessoalidade:** a atuação é voltada ao interesse público, sem favorecer nem perseguir ninguém.
- **Moralidade:** exige ética, probidade e boa-fé além da lei.
- **Publicidade:** os atos são públicos, salvo sigilo imprescindível à segurança da sociedade e do Estado.
- **Eficiência:** resultados com rapidez, qualidade e economia de recursos (EC 19/1998).

## Princípios implícitos
- **Supremacia do interesse público** sobre o particular.
- **Indisponibilidade do interesse público:** o agente não pode abrir mão do que pertence à coletividade.
- **Autotutela:** a Administração corrige seus próprios atos (Súmulas 346 e 473 do STF).
- **Razoabilidade e proporcionalidade** limitam a discricionariedade.

## Como cai em prova
- A banca troca "impessoalidade" por "pessoalidade" na pegadinha do art. 37.
- Súmula Vinculante 13 veda o nepotismo e decorre da moralidade e da impessoalidade.`],
  ["Fotossíntese: etapas e equação", ["Escola", "Biologia"], ["fotossíntese", "cloroplasto", "ciclo de Calvin"], "2026-11-23T09:30:00Z",
`# Fotossíntese: etapas e equação

## Ideia central
Plantas, algas e cianobactérias transformam **luz** em **energia química**, produzindo glicose e liberando oxigênio.

## Equação geral
$$6CO_2 + 6H_2O \\xrightarrow{luz} C_6H_{12}O_6 + 6O_2$$

## Etapa clara (fotoquímica)
- Acontece nos **tilacoides** do cloroplasto.
- A clorofila absorve luz e quebra a água (**fotólise**), liberando O₂.
- Produz **ATP** e **NADPH**.

## Etapa escura (ciclo de Calvin)
- Acontece no **estroma**.
- Usa ATP e NADPH para fixar o CO₂ e montar a glicose.
- A enzima **RuBisCO** faz a fixação do carbono.

## Fatores que limitam
- Intensidade da luz, concentração de CO₂ e temperatura.`],
  ["Listas e dicionários em Python", ["Tecnologia", "Python"], ["python", "listas", "dicionários"], "2026-11-22T20:05:00Z",
`# Listas e dicionários em Python

## Listas
- Coleção **ordenada** e mutável: \`frutas = ["maçã", "uva"]\`.
- \`append()\` adiciona no fim; \`pop()\` remove e devolve o último.
- Fatiamento: \`frutas[0:2]\` pega do índice 0 ao 1.

## Dicionários
- Pares **chave: valor**, acesso rápido por chave: \`aluno = {"nome": "Ana", "nota": 9}\`.
- \`aluno.get("idade", 0)\` evita erro quando a chave não existe.
- \`for k, v in aluno.items()\` percorre chaves e valores.

## Compreensões
- \`[x * 2 for x in numeros if x > 0]\` cria uma lista nova em uma linha.
- \`{p: len(p) for p in palavras}\` faz o mesmo para dicionários.

## Quando usar
- Lista quando a **ordem** importa; dicionário quando se busca por **nome**.`],
  ["Leis de Newton", ["Escola", "Física"], ["newton", "dinâmica", "força"], "2026-11-21T16:45:00Z",
`# Leis de Newton

## 1ª lei: inércia
- Sem força resultante, o corpo mantém repouso ou movimento retilíneo uniforme.

## 2ª lei: princípio fundamental
- $F = m \\cdot a$: a aceleração é proporcional à força e inversa à massa.
- Unidade de força: **newton (N)** = kg·m/s².

## 3ª lei: ação e reação
- Toda ação tem uma reação de mesma intensidade e sentido oposto, **em corpos diferentes**.

## Pegadinhas
- Ação e reação **não se anulam**, pois atuam em corpos distintos.
- Peso ($P = m \\cdot g$) não é massa: muda com a gravidade.`],
  ["Juros compostos", ["Concursos", "Matemática financeira"], ["juros", "montante", "capitalização"], "2026-11-20T11:20:00Z",
`# Juros compostos

## Fórmula
- $M = C \\cdot (1 + i)^t$, em que **M** é o montante, **C** o capital, **i** a taxa e **t** o tempo.

## Diferença para os juros simples
- Simples: o juro incide sempre sobre o capital inicial.
- Compostos: o juro incide sobre o capital **mais os juros acumulados** (juros sobre juros).

## Dicas de prova
- Taxa e tempo precisam estar na **mesma unidade**.
- Em poucos períodos os dois regimes são parecidos; em prazos longos, o composto cresce muito mais.
- Taxas equivalentes: $(1 + i_a) = (1 + i_m)^{12}$.`],
  ["Revolução Francesa", ["Escola", "História"], ["1789", "iluminismo", "burguesia"], "2026-11-19T18:00:00Z",
`# Revolução Francesa

## Causas
- Crise financeira da monarquia e fome no campo.
- Sociedade dividida em três estados, com o **Terceiro Estado** pagando quase todos os impostos.
- Ideias **iluministas** de liberdade, igualdade e soberania popular.

## Marcos
- **1789:** Queda da Bastilha e Declaração dos Direitos do Homem e do Cidadão.
- **1792-1794:** República e período do Terror (Robespierre).
- **1799:** Golpe do 18 Brumário leva Napoleão ao poder.

## Consequências
- Fim do absolutismo e dos privilégios feudais; avanço dos ideais burgueses.`],
  ["Modelo OSI em 7 camadas", ["Tecnologia", "Redes"], ["redes", "OSI", "protocolos"], "2026-11-18T13:15:00Z",
`# Modelo OSI em 7 camadas

## As camadas (de cima para baixo)
1. **Aplicação:** HTTP, DNS, SMTP.
2. **Apresentação:** formato, criptografia e compressão.
3. **Sessão:** abre, mantém e encerra conversas.
4. **Transporte:** TCP (confiável) e UDP (rápido).
5. **Rede:** endereçamento e roteamento (IP).
6. **Enlace:** quadros e endereço MAC.
7. **Física:** bits no meio (cabo, rádio).

## Macete
- "**A**ntes **A**prendi **S**ó **T**udo **R**ápido, **E**ntão **F**ui": mnemônico das camadas.`],
  ["Containers x máquinas virtuais", ["Tecnologia", "DevOps"], ["docker", "containers", "virtualização"], "2026-11-17T10:40:00Z",
`# Containers x máquinas virtuais

## Máquina virtual
- Cada VM roda um **sistema operacional completo** sobre um hipervisor.
- Isolamento forte, mas pesada: gigabytes e inicialização em minutos.

## Container
- Compartilha o **kernel do host** e isola só processos, rede e sistema de arquivos.
- Leve: megabytes e inicialização em segundos.

## Docker na prática
- **Imagem:** receita imutável; **container:** a imagem em execução.
- \`docker run -p 8080:80 nginx\` publica a porta 80 do container na 8080 do host.
- Dados que precisam durar vão em **volumes**.`],
  ["Média, mediana e moda", ["Concursos", "Estatística"], ["estatística", "medidas de posição"], "2026-11-16T15:00:00Z",
`# Média, mediana e moda

- **Média:** soma dos valores dividida pela quantidade; sensível a valores extremos.
- **Mediana:** valor central com os dados em ordem; em quantidade par, média dos dois centrais.
- **Moda:** valor que mais se repete; pode haver mais de uma ou nenhuma.

## Qual usar
- Renda e preços com valores muito altos: prefira a **mediana**.
- Dados em categorias (cor, marca): use a **moda**.`],
];

for (const [title, category, tags, date, body] of pages) {
  const id = randomUUID();
  const slug = title.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "");
  const dir = path.join(root, "library", ...category);
  fs.mkdirSync(dir, { recursive: true });
  const fm = [
    "---", `id: "${id}"`, `title: ${JSON.stringify(title)}`, 'type: "summary"', 'source: "video"', 'status: "published"',
    "category:", ...category.map((c) => `  - ${JSON.stringify(c)}`), "tags:", ...tags.map((t) => `  - ${JSON.stringify(t)}`),
    `createdAt: "${date}"`, `updatedAt: "${date}"`, "---", "",
  ].join("\n");
  fs.writeFileSync(path.join(dir, `${slug}_${id}.md`), fm + "\n" + body + "\n");
}

const settings = {
  version: 1,
  paths: { libraryDir: path.join(root, "library") },
  ai: { default: { providerId: "ollama", modelId: "llama3.1:8b" } },
  appearance: { theme, accent: "cyan" },
  updates: { autoCheck: false },
  support: { starPromptHidden: true },
  local: { ollama: { url: `http://127.0.0.1:${mockPort}` } },
};
fs.writeFileSync(path.join(root, "config", "settings.json"), JSON.stringify(settings, null, 2));
console.log("ok", root);

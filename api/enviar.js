// Recebe as respostas do formulário e envia por e-mail pela caixa da Hostinger.
// Variáveis de ambiente (configure na Vercel, nunca no código):
//   SMTP_USER  = contato@metamaximadigital.com.br
//   SMTP_PASS  = senha da caixa contato@
//   EMAIL_TO   = multiplanoss@gmail.com
const nodemailer = require("nodemailer");

const ORIGENS = [
  "https://bragancapedro00-lgtm.github.io",
];

function esc(v) {
  return String(v == null ? "" : v)
    .slice(0, 300)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

module.exports = async (req, res) => {
  const origin = req.headers.origin || "";
  if (ORIGENS.includes(origin) || /\.vercel\.app$/.test(origin)) {
    res.setHeader("Access-Control-Allow-Origin", origin);
    res.setHeader("Vary", "Origin");
  }
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");
  if (req.method === "OPTIONS") return res.status(204).end();
  if (req.method !== "POST") return res.status(405).json({ ok: false });

  let b = req.body || {};
  if (typeof b === "string") { try { b = JSON.parse(b); } catch (e) { b = {}; } }

  // campo escondido contra robôs: se vier preenchido, finge sucesso e não envia
  if (b.site) return res.status(200).json({ ok: true });

  const nome = String(b.nome || "").trim();
  const whats = String(b.whats || "").trim();
  const digitos = whats.replace(/\D/g, "");
  if (nome.length < 2 || digitos.length < 10) {
    return res.status(400).json({ ok: false, erro: "dados incompletos" });
  }

  const agora = new Date().toLocaleString("pt-BR", { timeZone: "America/Sao_Paulo" });
  const linhas = [
    ["Nome", nome],
    ["Idades", b.idades],
    ["WhatsApp", whats],
    ["Plano ativo", b.plano],
    ["Cidade", b.cidade],
    ["Data e hora", agora],
  ];

  const waLink = "https://wa.me/55" + digitos.replace(/^55/, "");
  const html =
    '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222">' +
    '<h2 style="color:#15833B;margin:0 0 12px">Nova cotação MedSênior</h2>' +
    '<table cellpadding="8" style="border-collapse:collapse">' +
    linhas.map(([k, v]) =>
      '<tr><td style="border:1px solid #ddd;background:#f3f8f4"><b>' + k + '</b></td>' +
      '<td style="border:1px solid #ddd">' + esc(v) + "</td></tr>").join("") +
    "</table>" +
    '<p style="margin-top:16px"><a href="' + waLink + '" style="background:#25D366;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Chamar no WhatsApp</a></p>' +
    "</div>";
  const texto = linhas.map(([k, v]) => k + ": " + (v || "")).join("\n") + "\n\nChamar no WhatsApp: " + waLink;

  try {
    const t = nodemailer.createTransport({
      host: "smtp.hostinger.com",
      port: 465,
      secure: true,
      auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
    });
    await t.sendMail({
      from: '"Cotação MedSênior" <' + process.env.SMTP_USER + ">",
      to: process.env.EMAIL_TO || "multiplanoss@gmail.com",
      subject: "Nova cotação MedSênior: " + nome.slice(0, 80),
      text: texto,
      html,
    });
    return res.status(200).json({ ok: true });
  } catch (e) {
    console.error("Falha ao enviar e-mail:", e && e.message);
    return res.status(500).json({ ok: false });
  }
};

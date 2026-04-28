// CARU CARS — SMS Lead Alerts via TextBelt (Netlify Function)
// Fans out a single lead to staff phone numbers.
// Called from contact.html, maria-chat.js, and send-application.php.

const TEXTBELT_KEY = process.env.TEXTBELT_KEY || '14b396b521b16b9d54ae8e4ffaa3d5c607163db8ECv4sLWu0pX8sSSArY5HjYKT1';
const AUTH_KEY = 'carucars-sms-2026';

const RECIPIENTS = [
  { name: 'Osvaldo Rodriguez', phone: '+13059654109' },
  { name: 'Armani',            phone: '+13059727159' },
];

const cors = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
  'Content-Type': 'application/json',
};

exports.handler = async (event) => {
  if (event.httpMethod === 'OPTIONS') {
    return { statusCode: 200, headers: cors, body: '' };
  }
  if (event.httpMethod !== 'POST') {
    return { statusCode: 405, headers: cors, body: JSON.stringify({ success: false, error: 'method' }) };
  }

  let d;
  try { d = JSON.parse(event.body || '{}'); }
  catch { return { statusCode: 400, headers: cors, body: JSON.stringify({ success: false, error: 'json' }) }; }

  if (d.key !== AUTH_KEY) {
    return { statusCode: 403, headers: cors, body: JSON.stringify({ success: false, error: 'forbidden' }) };
  }

  const source  = d.source || 'Website';
  const first   = d.first_name || '';
  const last    = d.last_name  || '';
  let   name    = (first + ' ' + last).trim();
  if (!name && d.name) name = d.name;
  const phone   = d.phone || '';
  const vehicle = d.vehicle_interest || '';
  const down    = d.down_payment || '';
  const pdfUrl  = d.pdf_url || '';
  const message = d.message || '';

  const lines = [`CaruCars: New ${source} lead`];
  if (name)    lines.push(name);
  if (phone)   lines.push(`Call: ${phone}`);
  if (vehicle) lines.push(`Vehicle: ${vehicle}`);
  if (down)    lines.push(`Down: $${down}`);
  if (pdfUrl)  lines.push(`PDF: ${pdfUrl}`);
  if (message && !pdfUrl && !vehicle) {
    const snippet = String(message).replace(/\s+/g, ' ').trim().slice(0, 100);
    if (snippet) lines.push(`Msg: ${snippet}`);
  }
  const body = lines.join('\n');

  const results = {};
  for (const r of RECIPIENTS) {
    try {
      const params = new URLSearchParams({
        phone:   r.phone,
        message: body,
        key:     TEXTBELT_KEY,
        sender:  'CaruCars',
      });
      const resp = await fetch('https://textbelt.com/text', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    params.toString(),
      });
      results[r.name] = await resp.json();
    } catch (e) {
      results[r.name] = { error: String(e) };
    }
  }

  return {
    statusCode: 200,
    headers: cors,
    body: JSON.stringify({ success: true, results }),
  };
};

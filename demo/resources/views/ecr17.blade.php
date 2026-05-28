<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ECR17 Debug Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <style>body{background:#0b0f14}</style>
</head>
<body class="bg-[#0b0f14] text-[#e6edf3]">
    <div id="root" class="min-h-screen"></div>

    <script type="text/babel" data-presets="react">
const { useState, useEffect, useRef, useCallback } = React;

const CSRF = document.querySelector('meta[name="csrf-token"]').content;

const COMMANDS = [
  { key: 'status', label: 'Status', letter: 's', fields: [] },
  { key: 'pay', label: 'Pay', letter: 'P', danger: true, fields: ['amount', 'paymentType', 'cardPresent', 'receipt'] },
  { key: 'payExtended', label: 'Pay (ext)', letter: 'X', danger: true, fields: ['amount', 'paymentType', 'cardPresent', 'receipt'] },
  { key: 'reverse', label: 'Reverse', letter: 'S', danger: true, fields: ['stan'] },
  { key: 'preAuth', label: 'Pre-auth', letter: 'p', danger: true, fields: ['amount', 'paymentType', 'cardPresent', 'receipt'] },
  { key: 'incrementalAuth', label: 'Incremental', letter: 'i', danger: true, fields: ['amount', 'preauth', 'receipt'] },
  { key: 'preAuthClosure', label: 'Pre-auth close', letter: 'c', danger: true, fields: ['amount', 'preauth', 'receipt'] },
  { key: 'verifyCard', label: 'Verify card', letter: 'H', fields: ['paymentType'] },
  { key: 'closeSession', label: 'Close session', letter: 'C', danger: true, fields: [] },
  { key: 'totals', label: 'Totals', letter: 'T', fields: [] },
  { key: 'sendLastResult', label: 'Send last (G)', letter: 'G', fields: [] },
  { key: 'enableEcrPrinting', label: 'ECR printing', letter: 'E', fields: ['enabled'] },
  { key: 'reprint', label: 'Reprint', letter: 'R', fields: ['toEcr'] },
  { key: 'vas', label: 'VAS', letter: 'K', fields: ['xml'] },
];

const LEVEL_COLOR = {
  info: 'text-slate-400', sent: 'text-blue-400', recv: 'text-slate-200',
  progress: 'text-amber-400', receipt: 'text-violet-400',
  ok: 'text-green-400', ko: 'text-amber-400', error: 'text-red-400',
};

const DEFAULT_CONFIG = {
  host: '', port: 1024, terminal_id: '', cash_register_id: '', lrc_mode: 'std',
  auto_reconnect: true, connection_timeout_ms: 5000, response_timeout_ms: 60000,
  ack_timeout_ms: 2000, retry_count: 3, retry_delay_ms: 200, receipt_drain_ms: 0,
};

function loadConfig() {
  try { return { ...DEFAULT_CONFIG, ...JSON.parse(localStorage.getItem('ecr17.config') || '{}') }; }
  catch { return { ...DEFAULT_CONFIG }; }
}

async function api(url, body) {
  const res = await fetch(url, {
    method: body ? 'POST' : 'GET',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  return res.json();
}

function App() {
  const [config, setConfig] = useState(loadConfig);
  const [showConfig, setShowConfig] = useState(true);
  const [busy, setBusy] = useState(false);
  const [logs, setLogs] = useState([]);
  const [sheet, setSheet] = useState(null);
  const [toast, setToast] = useState(null);

  useEffect(() => { localStorage.setItem('ecr17.config', JSON.stringify(config)); }, [config]);

  // Poll logs.
  useEffect(() => {
    let alive = true;
    const tick = async () => {
      try { const r = await api('/ecr17/logs'); if (alive) setLogs(r.entries || []); } catch {}
    };
    tick();
    const id = setInterval(tick, 1200);
    return () => { alive = false; clearInterval(id); };
  }, []);

  const set = (k, v) => setConfig((c) => ({ ...c, [k]: v }));

  const run = useCallback(async (key, params) => {
    setBusy(true);
    try {
      const r = await api('/ecr17/command/' + key, { config, params: params || {} });
      if (r.ok) {
        const outcome = r.result && r.result.outcome;
        setToast(outcome && outcome !== 'ok' ? { t: 'KO (' + outcome + ')', c: 'bg-amber-500' } : { t: 'OK', c: 'bg-green-600' });
      } else {
        setToast({ t: 'Error — see log', c: 'bg-red-600' });
      }
    } finally {
      setBusy(false);
      setTimeout(() => setToast(null), 2500);
    }
  }, [config]);

  const onPick = (cmd) => { if (cmd.fields.length === 0) run(cmd.key, {}); else setSheet(cmd); };

  const clearLogs = async () => { await api('/ecr17/clear-logs', {}); setLogs([]); };

  return (
    <div className="flex flex-col h-screen">
      <header className="px-4 py-3 border-b border-[#243040] flex items-center justify-between">
        <h1 className="font-extrabold text-lg">ECR17 Debug Console</h1>
        <button onClick={() => run('status', {})} disabled={busy}
          className="px-3 py-1.5 rounded-md bg-blue-600 text-white text-sm font-bold disabled:opacity-50">Ping (status)</button>
      </header>

      <div className="overflow-y-auto" style={{ maxHeight: '46%' }}>
        <div className="px-4 py-3 border-b border-[#243040]">
          <button onClick={() => setShowConfig((s) => !s)} className="w-full flex justify-between text-left font-bold">
            <span>Configuration</span><span>{showConfig ? '▾' : '▸'}</span>
          </button>
          {showConfig && (
            <div className="grid grid-cols-2 gap-2 mt-3">
              <Field label="Host" value={config.host} onChange={(v) => set('host', v)} ph="192.168.1.50" />
              <Field label="Port" value={config.port} onChange={(v) => set('port', +v || 0)} type="number" />
              <Field label="Terminal ID" value={config.terminal_id} onChange={(v) => set('terminal_id', v)} />
              <Field label="Cash register ID" value={config.cash_register_id} onChange={(v) => set('cash_register_id', v)} />
              <Enum label="LRC mode" value={config.lrc_mode} options={['std','stx','noext','stx_noext']} onChange={(v) => set('lrc_mode', v)} />
              <Bool label="Auto reconnect" value={config.auto_reconnect} onChange={(v) => set('auto_reconnect', v)} />
              <Field label="Response timeout (ms)" value={config.response_timeout_ms} onChange={(v) => set('response_timeout_ms', +v || 0)} type="number" />
              <Field label="ACK timeout (ms)" value={config.ack_timeout_ms} onChange={(v) => set('ack_timeout_ms', +v || 0)} type="number" />
            </div>
          )}
        </div>

        <div className="px-4 py-3">
          <div className="font-bold mb-2">Commands</div>
          <div className="flex flex-wrap gap-2">
            {COMMANDS.map((cmd) => (
              <button key={cmd.key} disabled={busy} onClick={() => onPick(cmd)}
                className={'flex items-center gap-1.5 px-3 py-1.5 rounded-md border text-sm disabled:opacity-50 '
                  + (cmd.danger ? 'border-red-500' : 'border-[#243040]') + ' bg-[#1a2230]'}>
                <span className="w-5 h-5 grid place-items-center rounded bg-[#0b0f14] text-blue-400 text-xs font-bold">{cmd.letter}</span>
                {cmd.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      <LogConsole logs={logs} onClear={clearLogs} />

      {sheet && <ParamsSheet cmd={sheet} onClose={() => setSheet(null)} onSubmit={(p) => { run(sheet.key, p); setSheet(null); }} />}
      {busy && <div className="fixed inset-0 bg-black/70 grid place-items-center z-40"><div className="bg-[#121821] rounded-xl px-8 py-6 text-center"><div className="animate-spin h-8 w-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto"></div><div className="mt-3">Working…</div></div></div>}
      {toast && <div className={'fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-lg font-bold text-white ' + toast.c}>{toast.t}</div>}
    </div>
  );
}

function LogConsole({ logs, onClear }) {
  const data = [...logs].reverse();
  return (
    <div className="flex-1 bg-[#0b0f14] flex flex-col min-h-0">
      <div className="flex justify-between items-center px-4 py-2 border-t border-[#243040]">
        <span className="font-bold text-sm">Log ({logs.length})</span>
        <button onClick={onClear} className="px-3 py-1 rounded text-sm bg-[#1a2230]">Clear</button>
      </div>
      <div className="flex-1 overflow-y-auto px-4 pb-4 font-mono text-xs">
        {data.length === 0 && <div className="text-slate-500 text-center mt-6">No activity yet. Configure and run a command.</div>}
        {data.map((e) => (
          <div key={e.id} className="flex gap-2 py-1">
            <span className="text-slate-500 w-14">{new Date(e.ts).toLocaleTimeString()}</span>
            <span className={'w-14 ' + (LEVEL_COLOR[e.level] || 'text-slate-300')}>{e.level}</span>
            <div className="flex-1">
              <div className={LEVEL_COLOR[e.level] || 'text-slate-200'}>{e.label}</div>
              {e.detail && <div className="text-slate-500 break-all">{e.detail}</div>}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function ParamsSheet({ cmd, onClose, onSubmit }) {
  const [p, setP] = useState({ amount: '', paymentType: 'auto', cardAlreadyPresent: false, receiptText: '', stan: '', originalPreAuthCode: '', enabled: false, toEcr: false, xmlRequest: '' });
  const set = (k, v) => setP((s) => ({ ...s, [k]: v }));
  const has = (f) => cmd.fields.includes(f);
  const submit = () => {
    const out = {};
    if (has('amount')) out.amountCents = Math.round(parseFloat(String(p.amount).replace(',', '.')) * 100) || 0;
    if (has('paymentType')) out.paymentType = p.paymentType;
    if (has('cardPresent')) out.cardAlreadyPresent = p.cardAlreadyPresent;
    if (has('receipt')) out.receiptText = p.receiptText;
    if (has('stan')) out.stan = p.stan;
    if (has('preauth')) out.originalPreAuthCode = p.originalPreAuthCode;
    if (has('enabled')) out.enabled = p.enabled;
    if (has('toEcr')) out.toEcr = p.toEcr;
    if (has('xml')) out.xmlRequest = p.xmlRequest;
    onSubmit(out);
  };
  const needAmount = has('amount') && (!p.amount || parseFloat(String(p.amount)) <= 0);
  return (
    <div className="fixed inset-0 bg-black/70 z-50 flex items-end" onClick={onClose}>
      <div className="bg-[#121821] w-full rounded-t-2xl p-5 max-h-[80%] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="flex justify-between items-center mb-3"><h2 className="font-bold text-lg">{cmd.label}</h2><button onClick={onClose}>✕</button></div>
        {has('amount') && <Field label="Amount (€)" value={p.amount} onChange={(v) => set('amount', v)} type="text" ph="0.00" />}
        {has('paymentType') && <Enum label="Card type" value={p.paymentType} options={['auto','debit','credit','other']} onChange={(v) => set('paymentType', v)} />}
        {has('cardPresent') && <Bool label="Card already present" value={p.cardAlreadyPresent} onChange={(v) => set('cardAlreadyPresent', v)} />}
        {has('receipt') && <Field label="Receipt text" value={p.receiptText} onChange={(v) => set('receiptText', v)} />}
        {has('stan') && <Field label="STAN (blank = last)" value={p.stan} onChange={(v) => set('stan', v)} />}
        {has('preauth') && <Field label="Original pre-auth code" value={p.originalPreAuthCode} onChange={(v) => set('originalPreAuthCode', v)} />}
        {has('enabled') && <Bool label="Enabled" value={p.enabled} onChange={(v) => set('enabled', v)} />}
        {has('toEcr') && <Bool label="To ECR" value={p.toEcr} onChange={(v) => set('toEcr', v)} />}
        {has('xml') && <Field label="XML request" value={p.xmlRequest} onChange={(v) => set('xmlRequest', v)} />}
        <button disabled={needAmount} onClick={submit}
          className={'mt-3 w-full py-3 rounded-md font-bold text-white disabled:opacity-50 ' + (cmd.danger ? 'bg-red-600' : 'bg-blue-600')}>
          {cmd.danger ? 'Run (financial)' : 'Run'}
        </button>
      </div>
    </div>
  );
}

function Field({ label, value, onChange, type = 'text', ph }) {
  return (
    <label className="block mb-2 text-sm">
      <span className="text-slate-400">{label}</span>
      <input type={type} value={value} placeholder={ph} onChange={(e) => onChange(e.target.value)}
        className="mt-1 w-full bg-[#1a2230] border border-[#243040] rounded px-3 py-2 font-mono" />
    </label>
  );
}
function Enum({ label, value, options, onChange }) {
  return (
    <label className="block mb-2 text-sm">
      <span className="text-slate-400">{label}</span>
      <select value={value} onChange={(e) => onChange(e.target.value)} className="mt-1 w-full bg-[#1a2230] border border-[#243040] rounded px-3 py-2">
        {options.map((o) => <option key={o} value={o}>{o}</option>)}
      </select>
    </label>
  );
}
function Bool({ label, value, onChange }) {
  return (
    <label className="flex items-center justify-between mb-2 text-sm">
      <span className="text-slate-400">{label}</span>
      <input type="checkbox" checked={value} onChange={(e) => onChange(e.target.checked)} className="h-5 w-5" />
    </label>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
    </script>
</body>
</html>

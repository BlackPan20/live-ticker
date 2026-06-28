const STORE_KEY = 'tournament_data_v1';

function defaultData(){
  return {
    tournamentName: 'Mein Turnier',
    teams: [],
    groups: [],
    matches: [
      { id: 'm1', label: 'Spiel 1' },
      { id: 'm2', label: 'Spiel 2' },
      { id: 'm3', label: 'Spiel 3' }
    ],
    activeMatchId: 'm1',
    activeMatchNote: '',
    updatedAt: new Date().toISOString()
  };
}

async function loadData(){
  const raw = localStorage.getItem(STORE_KEY);
  if(!raw) return defaultData();
  try { return JSON.parse(raw); } catch { return defaultData(); }
}

async function saveData(data){
  data.updatedAt = new Date().toISOString();
  localStorage.setItem(STORE_KEY, JSON.stringify(data));
  return data;
}

function renderPublic(data){
  const el = document.getElementById('publicState');
  if(!el) return;

  el.innerHTML = `<p><strong>${data.tournamentName}</strong></p>` +
    (data.groups.length
      ? data.groups.map(g => `
          <div class="group">
            <h3>${g.name}</h3>
            <ul>${g.teams.map(t => `<li>${t.name} – ${t.points ?? 0} P</li>`).join('')}</ul>
          </div>`).join('')
      : '<p>Noch keine Gruppen erstellt.</p>');
}

function renderTicker(data, targetId='tickerState'){
  const el = document.getElementById(targetId);
  if(!el) return;

  const active = data.matches.find(m => m.id === data.activeMatchId)?.label || 'Kein Spiel gewählt';
  el.innerHTML = `
    <div class="ticker-box">
      <h3>${active}</h3>
      <p>${data.activeMatchNote || 'Kein Hinweis'}</p>
      <small>Letzte Änderung: ${new Date(data.updatedAt).toLocaleString('de-DE')}</small>
    </div>`;
}

function renderAdminState(data){
  const el = document.getElementById('adminState');
  if(!el) return;

  el.innerHTML = `
    <div class="grid two">
      <div>
        <h4>Teams</h4>
        <ul>${data.teams.map(t => `<li>${t.name}</li>`).join('') || '<li>Keine Teams</li>'}</ul>
      </div>
      <div>
        <h4>Gruppen</h4>
        ${data.groups.map(g => `
          <div class="group">
            <strong>${g.name}</strong>
            <ul>${g.teams.map(t => `<li>${t.name} – ${t.points ?? 0} P, ${t.goals ?? 0} Tore</li>`).join('')}</ul>
          </div>`).join('') || '<p>Keine Gruppen</p>'}
      </div>
    </div>`;
}

function renderMatchOptions(data){
  const sel = document.getElementById('matchSelect');
  if(!sel) return;
  sel.innerHTML = data.matches.map(m =>
    `<option value="${m.id}" ${m.id === data.activeMatchId ? 'selected' : ''}>${m.label}</option>`
  ).join('');
}

async function boot(){
  const data = await loadData();
  renderPublic(data);
  renderTicker(data, 'tickerState');
  renderTicker(data, 'tickerPage');
  renderMatchOptions(data);
}

boot();
setInterval(boot, 1500);
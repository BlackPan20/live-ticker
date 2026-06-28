const ADMIN_KEY = 'tournament_admin_logged_in';
const passwordHash = '937e8d5fbb48bd4949536cd65b8d35c426b80d2f830c5c308e2cdec422ae2244';

async function sha256(text){
  const data = new TextEncoder().encode(text);
  const hashBuffer = await crypto.subtle.digest('SHA-256', data);
  return [...new Uint8Array(hashBuffer)]
    .map(b => b.toString(16).padStart(2, '0'))
    .join('');
}

function showAdmin(ok){
  document.getElementById('loginCard').classList.toggle('hidden', ok);
  document.getElementById('adminCard').classList.toggle('hidden', !ok);
}

async function verify(){
  const stored = sessionStorage.getItem(ADMIN_KEY);
  showAdmin(stored === '1');
}

document.getElementById('loginForm').addEventListener('submit', async e => {
  e.preventDefault();
  const pw = document.getElementById('password').value;
  const h = await sha256(pw);

  if(h === passwordHash){
    sessionStorage.setItem(ADMIN_KEY, '1');
    document.getElementById('loginMsg').textContent = 'Login erfolgreich';
    showAdmin(true);
    initAdmin();
  } else {
    document.getElementById('loginMsg').textContent = 'Falsches Passwort';
  }
});

document.getElementById('logoutBtn').addEventListener('click', () => {
  sessionStorage.removeItem(ADMIN_KEY);
  location.reload();
});

let adminTimer;
async function initAdmin(){
  if(adminTimer) return;
  adminTimer = setInterval(refreshAdminView, 1200);
  await refreshAdminView();
}

async function refreshAdminView(){
  const data = await loadData();
  renderAdminState(data);
  renderMatchOptions(data);
}

document.getElementById('teamForm').addEventListener('submit', async e => {
  e.preventDefault();
  const data = await loadData();
  data.teams.push({ id: crypto.randomUUID(), name: teamName.value.trim() });
  teamName.value = '';
  await saveData(data);
  refreshAdminView();
});

document.getElementById('generateGroupsBtn').addEventListener('click', async () => {
  const data = await loadData();
  const shuffled = [...data.teams].sort(() => Math.random() - 0.5);
  const groupCount = Math.max(2, Math.ceil(shuffled.length / 4));
  const groups = Array.from({ length: groupCount }, (_, i) => ({
    id: 'g' + (i + 1),
    name: 'Gruppe ' + String.fromCharCode(65 + i),
    teams: []
  }));
  shuffled.forEach((team, i) => groups[i % groupCount].teams.push({ ...team, points: 0, goals: 0 }));
  data.groups = groups;
  await saveData(data);
  refreshAdminView();
});

document.getElementById('matchForm').addEventListener('submit', async e => {
  e.preventDefault();
  const data = await loadData();
  data.activeMatchId = matchSelect.value;
  data.activeMatchNote = matchNote.value.trim();
  await saveData(data);
  refreshAdminView();
});

document.getElementById('saveBtn').addEventListener('click', async () => {
  const data = await loadData();
  await saveData(data);
  refreshAdminView();
});

document.getElementById('resetBtn').addEventListener('click', async () => {
  if(confirm('Wirklich alles zurücksetzen?')){
    await saveData(defaultData());
    refreshAdminView();
  }
});

showAdmin(sessionStorage.getItem(ADMIN_KEY) === '1');
if(sessionStorage.getItem(ADMIN_KEY) === '1') initAdmin();
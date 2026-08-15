const ADMIN_KEY = 'tournament_admin_logged_in';
const passwordHash = '937e8d5fbb48bd4949536cd65b8d35c426b80d2f830c5c308e2cdec422ae2244';

const loginForm = document.getElementById('loginForm');
const loginMsg = document.getElementById('loginMsg');
const loginCard = document.getElementById('loginCard');
const adminCard = document.getElementById('adminCard');
const logoutBtn = document.getElementById('logoutBtn');
const teamForm = document.getElementById('teamForm');
const teamNameInput = document.getElementById('teamName');
const matchForm = document.getElementById('matchForm');
const matchSelect = document.getElementById('matchSelect');
const matchNoteInput = document.getElementById('matchNote');
const matchCreateForm = document.getElementById('matchCreateForm');
const matchStageSelect = document.getElementById('matchStage');
const matchLabelInput = document.getElementById('matchLabel');
const homeTeamSelect = document.getElementById('homeTeamSelect');
const awayTeamSelect = document.getElementById('awayTeamSelect');
const createMatchNoteInput = document.getElementById('createMatchNote');
const generateGroupsBtn = document.getElementById('generateGroupsBtn');
const saveBtn = document.getElementById('saveBtn');
const resetBtn = document.getElementById('resetBtn');
const goalControls = document.getElementById('goalControls');

async function sha256(text){
  const data = new TextEncoder().encode(text);
  const hashBuffer = await crypto.subtle.digest('SHA-256', data);
  return [...new Uint8Array(hashBuffer)]
    .map(b => b.toString(16).padStart(2, '0'))
    .join('');
}

function showAdmin(ok){
  loginCard.classList.toggle('hidden', ok);
  adminCard.classList.toggle('hidden', !ok);
}

async function verify(){
  const stored = sessionStorage.getItem(ADMIN_KEY);
  showAdmin(stored === '1');
}

loginForm.addEventListener('submit', async e => {
  e.preventDefault();
  const pw = document.getElementById('password').value;
  const h = await sha256(pw);

  if(h === passwordHash){
    window.currentAdminPassword = pw; // Speichere Passwort für API
    sessionStorage.setItem(ADMIN_KEY, '1');
    loginMsg.textContent = 'Login erfolgreich';
    showAdmin(true);
    initAdmin();
  } else {
    loginMsg.textContent = 'Falsches Passwort';
  }
});

logoutBtn.addEventListener('click', () => {
  window.currentAdminPassword = ''; // Lösche Passwort
  sessionStorage.removeItem(ADMIN_KEY);
  location.reload();
});

let adminTimer;
async function initAdmin(){
  if(adminTimer) return;
  adminTimer = setInterval(refreshAdminView, 1200);
  await refreshAdminView();
}

function renderTeamSelectOptions(data){
  const previousHomeValue = homeTeamSelect.value;
  const previousAwayValue = awayTeamSelect.value;
  const qualifiedTeams = getQualifiedTeams(data, 2);
  const qualifiedIds = new Set(qualifiedTeams.map(team => team.id));
  const qualifiedOptions = qualifiedTeams.length
    ? `<optgroup label="Qualifiziert">${qualifiedTeams.map(team => `<option value="${team.id}">${formatTeamOption(team)}</option>`).join('')}</optgroup>`
    : '';
  const otherTeams = data.teams.filter(team => !qualifiedIds.has(team.id));
  const allOptions = `<optgroup label="Alle Teams">${otherTeams.map(team => `<option value="${team.id}">${formatTeamOption(team)}</option>`).join('')}</optgroup>`;

  const markup = ['<option value="">Team auswählen</option>', qualifiedOptions, allOptions].join('');
  homeTeamSelect.innerHTML = markup;
  awayTeamSelect.innerHTML = markup;

  if(previousHomeValue && data.teams.some(team => team.id === previousHomeValue)){
    homeTeamSelect.value = previousHomeValue;
  }

  if(previousAwayValue && data.teams.some(team => team.id === previousAwayValue)){
    awayTeamSelect.value = previousAwayValue;
  }
}

async function refreshAdminView(){
  const data = await loadData();
  renderAdminState(data);
  renderMatchOptions(data);
  renderGoalControls(data);
  renderTeamSelectOptions(data);
}

matchSelect.addEventListener('change', async () => {
  const data = await loadData();
  data.activeMatchId = matchSelect.value;
  await saveData(data);
  refreshAdminView();
});

teamForm.addEventListener('submit', async e => {
  e.preventDefault();
  const data = await loadData();
  data.teams.push({ id: crypto.randomUUID(), name: teamNameInput.value.trim() });
  teamNameInput.value = '';
  await saveData(data);
  refreshAdminView();
});

generateGroupsBtn.addEventListener('click', async () => {
  await generateGroupsAndFixtures();
  refreshAdminView();
});

matchForm.addEventListener('submit', async e => {
  e.preventDefault();
  const data = await loadData();
  data.activeMatchNote = matchNoteInput.value.trim();
  await saveData(data);
  refreshAdminView();
});

matchCreateForm.addEventListener('submit', async e => {
  e.preventDefault();
  await createMatch({
    stage: matchStageSelect.value,
    label: matchLabelInput.value,
    homeTeamId: homeTeamSelect.value,
    awayTeamId: awayTeamSelect.value,
    note: createMatchNoteInput.value
  });

  matchLabelInput.value = '';
  createMatchNoteInput.value = '';
  refreshAdminView();
});

goalControls.addEventListener('click', async e => {
  const button = e.target.closest('[data-goal-side]');
  const confirmButton = e.target.closest('[data-action="confirm-match"]');

  if(confirmButton){
    const data = await loadData();
    await confirmMatch(data.activeMatchId);
    refreshAdminView();
    return;
  }

  if(!button) return;

  const side = button.dataset.goalSide;
  const data = await loadData();
  await addGoal(data.activeMatchId, side);
  refreshAdminView();
});

saveBtn.addEventListener('click', async () => {
  const data = await loadData();
  await saveData(data);
  refreshAdminView();
});

resetBtn.addEventListener('click', async () => {
  if(confirm('Wirklich alles zurücksetzen?')){
    await saveData(defaultData());
    refreshAdminView();
  }
});

showAdmin(sessionStorage.getItem(ADMIN_KEY) === '1');
if(sessionStorage.getItem(ADMIN_KEY) === '1') initAdmin();
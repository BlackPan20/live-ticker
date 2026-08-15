const STORE_KEY = 'tournament_data_v1';

const STAGE_LABELS = {
  group: 'Gruppenphase',
  quarterfinal: 'Viertelfinale',
  semifinal: 'Halbfinale',
  final: 'Finale',
  placement: 'Spiel um Platz 3',
  custom: 'Spiel'
};

function defaultData(){
  return {
    tournamentName: 'Mein Turnier',
    teams: [],
    groups: [],
    matches: [],
    activeMatchId: '',
    activeMatchNote: '',
    updatedAt: new Date().toISOString()
  };
}

function normalizeTeam(team = {}){
  return {
    id: team.id || crypto.randomUUID(),
    name: team.name || 'Unbenanntes Team',
    points: Number(team.points ?? 0),
    goals: Number(team.goals ?? 0),
    played: Number(team.played ?? 0),
    goalDifference: Number(team.goalDifference ?? 0),
    groupId: team.groupId || ''
  };
}

function normalizeGroup(group = {}){
  return {
    id: group.id || crypto.randomUUID(),
    name: group.name || 'Gruppe',
    teams: Array.isArray(group.teams) ? group.teams.map(normalizeTeam) : []
  };
}

function normalizeMatch(match = {}, index = 0){
  const stage = match.stage || (index === 0 ? 'final' : 'custom');
  const label = match.label || STAGE_LABELS[stage] || 'Spiel';

  return {
    id: match.id || crypto.randomUUID(),
    stage,
    label,
    groupId: match.groupId || '',
    round: match.round || '',
    status: match.status || 'scheduled',
    homeTeamId: match.homeTeamId || '',
    awayTeamId: match.awayTeamId || '',
    homeGoals: Number(match.homeGoals ?? 0),
    awayGoals: Number(match.awayGoals ?? 0),
    note: match.note || '',
    confirmedAt: match.confirmedAt || ''
  };
}

function normalizeData(rawData){
  const base = defaultData();
  const data = rawData && typeof rawData === 'object' ? rawData : {};
  const matches = Array.isArray(data.matches) && data.matches.length
    ? data.matches.map(normalizeMatch)
    : base.matches;

  return {
    ...base,
    ...data,
    teams: Array.isArray(data.teams) ? data.teams.map(normalizeTeam) : base.teams,
    groups: Array.isArray(data.groups) ? data.groups.map(normalizeGroup) : base.groups,
    matches,
    activeMatchId: data.activeMatchId || matches[0]?.id || base.activeMatchId,
    activeMatchNote: data.activeMatchNote || '',
    updatedAt: data.updatedAt || base.updatedAt
  };
}

function getTeamById(data, teamId){
  return data.teams.find(team => team.id === teamId) || null;
}

function getMatchTitle(data, match){
  if(!match) return 'Kein Spiel gewählt';

  const stageLabel = STAGE_LABELS[match.stage] || match.label || 'Spiel';
  const homeTeam = getTeamById(data, match.homeTeamId)?.name;
  const awayTeam = getTeamById(data, match.awayTeamId)?.name;

  if(homeTeam && awayTeam){
    return `${stageLabel}: ${homeTeam} vs ${awayTeam}`;
  }

  return match.label || stageLabel;
}

function getMatchScore(match){
  return `${Number(match.homeGoals ?? 0)}:${Number(match.awayGoals ?? 0)}`;
}

function getTeamLabel(team){
  if(!team) return 'Unbekannt';
  return team.name || 'Unbekannt';
}

function cloneTeamsForGroup(groupId, teams){
  return teams.map(team => ({
    ...normalizeTeam(team),
    groupId,
    points: 0,
    goals: 0,
    played: 0,
    goalDifference: 0
  }));
}

function buildGroupFixtures(groups){
  const fixtures = [];

  groups.forEach(group => {
    const groupTeams = group.teams || [];
    for(let i = 0; i < groupTeams.length; i += 1){
      for(let j = i + 1; j < groupTeams.length; j += 1){
        fixtures.push({
          id: crypto.randomUUID(),
          stage: 'group',
          groupId: group.id,
          round: 'group',
          status: 'scheduled',
          label: `${group.name}: ${groupTeams[i].name} vs ${groupTeams[j].name}`,
          homeTeamId: groupTeams[i].id,
          awayTeamId: groupTeams[j].id,
          homeGoals: 0,
          awayGoals: 0,
          note: ''
        });
      }
    }
  });

  return fixtures;
}

function getGroupStandings(data, groupId){
  const group = data.groups.find(entry => entry.id === groupId);
  if(!group) return [];

  const standings = cloneTeamsForGroup(groupId, group.teams);
  const applyMatch = match => {
    if(match.stage !== 'group' || match.groupId !== groupId || match.status !== 'confirmed') return;

    const homeTeam = standings.find(team => team.id === match.homeTeamId);
    const awayTeam = standings.find(team => team.id === match.awayTeamId);
    if(!homeTeam || !awayTeam) return;

    const homeGoals = Number(match.homeGoals ?? 0);
    const awayGoals = Number(match.awayGoals ?? 0);

    homeTeam.played += 1;
    awayTeam.played += 1;
    homeTeam.goals += homeGoals;
    awayTeam.goals += awayGoals;
    homeTeam.goalDifference += homeGoals - awayGoals;
    awayTeam.goalDifference += awayGoals - homeGoals;

    if(homeGoals > awayGoals){
      homeTeam.points += 3;
    } else if(awayGoals > homeGoals){
      awayTeam.points += 3;
    } else {
      homeTeam.points += 1;
      awayTeam.points += 1;
    }
  };

  data.matches.forEach(applyMatch);

  return standings.sort((left, right) =>
    right.points - left.points ||
    right.goalDifference - left.goalDifference ||
    right.goals - left.goals ||
    left.name.localeCompare(right.name, 'de')
  );
}

function getQualifiedTeams(data, perGroup = 2){
  return data.groups.flatMap(group => getGroupStandings(data, group.id).slice(0, perGroup));
}

function isMatchConfirmed(match){
  return match.status === 'confirmed';
}

function recalculateStandings(data){
  data.groups = data.groups.map(group => ({
    ...group,
    teams: cloneTeamsForGroup(group.id, group.teams)
  }));

  data.matches.forEach(match => {
    if(match.stage !== 'group' || match.status !== 'confirmed') return;

    const group = data.groups.find(entry => entry.id === match.groupId);
    if(!group) return;

    const homeTeam = group.teams.find(team => team.id === match.homeTeamId);
    const awayTeam = group.teams.find(team => team.id === match.awayTeamId);
    if(!homeTeam || !awayTeam) return;

    const homeGoals = Number(match.homeGoals ?? 0);
    const awayGoals = Number(match.awayGoals ?? 0);

    homeTeam.played += 1;
    awayTeam.played += 1;
    homeTeam.goals += homeGoals;
    awayTeam.goals += awayGoals;
    homeTeam.goalDifference += homeGoals - awayGoals;
    awayTeam.goalDifference += awayGoals - homeGoals;

    if(homeGoals > awayGoals){
      homeTeam.points += 3;
    } else if(awayGoals > homeGoals){
      awayTeam.points += 3;
    } else {
      homeTeam.points += 1;
      awayTeam.points += 1;
    }
  });

  data.groups.forEach(group => {
    group.teams.sort((left, right) =>
      right.points - left.points ||
      right.goalDifference - left.goalDifference ||
      right.goals - left.goals ||
      left.name.localeCompare(right.name, 'de')
    );
  });

  return data;
}

async function loadData(){
  const raw = localStorage.getItem(STORE_KEY);
  if(!raw) return normalizeData(defaultData());

  try {
    return normalizeData(JSON.parse(raw));
  } catch {
    return normalizeData(defaultData());
  }
}

async function saveData(data){
  const normalized = normalizeData(data);
  normalized.updatedAt = new Date().toISOString();
  localStorage.setItem(STORE_KEY, JSON.stringify(normalized));
  return normalized;
}

async function addGoal(matchId, side){
  const data = await loadData();
  const match = data.matches.find(entry => entry.id === matchId);

  if(!match) return data;

  if(side === 'home'){
    match.homeGoals = Number(match.homeGoals ?? 0) + 1;
  }

  if(side === 'away'){
    match.awayGoals = Number(match.awayGoals ?? 0) + 1;
  }

  if(matchId === data.activeMatchId){
    data.activeMatchNote = `${getMatchTitle(data, match)} ${getMatchScore(match)}`;
  }

  return saveData(data);
}

async function confirmMatch(matchId){
  const data = await loadData();
  const match = data.matches.find(entry => entry.id === matchId);

  if(!match) return data;

  match.status = 'confirmed';
  match.confirmedAt = new Date().toISOString();
  data.activeMatchNote = `${getMatchTitle(data, match)} ${getMatchScore(match)} bestätigt`;
  recalculateStandings(data);

  return saveData(data);
}

async function createMatch(input){
  const data = await loadData();
  const stage = input.stage || 'custom';
  const label = input.label?.trim() || STAGE_LABELS[stage] || 'Spiel';

  data.matches.push({
    id: crypto.randomUUID(),
    stage,
    label,
    groupId: input.groupId || '',
    round: input.round || '',
    status: input.status || 'scheduled',
    homeTeamId: input.homeTeamId || '',
    awayTeamId: input.awayTeamId || '',
    homeGoals: 0,
    awayGoals: 0,
    note: input.note?.trim() || '',
    confirmedAt: ''
  });
  data.activeMatchId = data.matches[data.matches.length - 1].id;
  data.activeMatchNote = input.note?.trim() || '';

  return saveData(data);
}

async function generateGroupsAndFixtures(teamList){
  const data = await loadData();
  const teams = (teamList || data.teams).map(normalizeTeam);
  if(!teams.length) return data;

  const shuffled = [...teams].sort(() => Math.random() - 0.5);
  const groupCount = Math.max(2, Math.ceil(shuffled.length / 4));
  const groups = Array.from({ length: groupCount }, (_, index) => ({
    id: `g${index + 1}`,
    name: `Gruppe ${String.fromCharCode(65 + index)}`,
    teams: []
  }));

  shuffled.forEach((team, index) => {
    const groupIndex = index % groupCount;
    groups[groupIndex].teams.push({
      ...team,
      groupId: groups[groupIndex].id,
      points: 0,
      goals: 0,
      played: 0,
      goalDifference: 0
    });
  });

  data.groups = groups;
  data.matches = data.matches.filter(match => match.stage !== 'group');
  data.matches.push(...buildGroupFixtures(groups));
  data.activeMatchId = data.matches.find(match => match.stage === 'group')?.id || data.activeMatchId || '';
  recalculateStandings(data);

  return saveData(data);
}

function formatTeamOption(team, prefix = ''){
  const points = Number(team.points ?? 0);
  const goals = Number(team.goals ?? 0);
  const played = Number(team.played ?? 0);
  const name = prefix ? `${prefix} ${team.name}` : team.name;
  return `${name} - ${points} P, ${goals} Tore, ${played} Spiele`;
}

function renderGroupSchedule(data){
  const el = document.getElementById('groupSchedule');
  if(!el) return;

  if(!data.groups.length){
    el.innerHTML = '<p>Erst Teams anlegen und Gruppen generieren.</p>';
    return;
  }

  el.innerHTML = data.groups.map(group => {
    const standings = getGroupStandings(data, group.id);
    const groupMatches = data.matches.filter(match => match.stage === 'group' && match.groupId === group.id);

    return `
      <div class="panel schedule-panel">
        <h4>${group.name}</h4>
        <div class="schedule-columns">
          <div>
            <h5>Spiele</h5>
            <ul class="match-list">
              ${groupMatches.map(match => `
                <li class="match-item ${match.id === data.activeMatchId ? 'active' : ''}">
                  <strong>${getMatchTitle(data, match)}</strong>
                  <span>${getMatchScore(match)} ${isMatchConfirmed(match) ? 'bestätigt' : 'live'}</span>
                </li>`).join('') || '<li>Noch keine Spiele</li>'}
            </ul>
          </div>
          <div>
            <h5>Tabelle</h5>
            <ul class="standings-list">
              ${standings.map(team => `<li>${team.name} - ${team.points} P, ${team.goals} Tore</li>`).join('') || '<li>Noch keine Tabelle</li>'}
            </ul>
          </div>
        </div>
      </div>`;
  }).join('');
}

function renderPublic(data){
  const el = document.getElementById('publicState');
  if(!el) return;

  const activeMatch = data.matches.find(match => match.id === data.activeMatchId);
  const groupTeams = data.groups.flatMap(group => group.teams.map(team => ({ ...team, groupName: group.name })));

  el.innerHTML = `
    <div class="stack">
      <div class="hero-card">
        <p class="eyebrow">Turnierübersicht</p>
        <h3>${data.tournamentName}</h3>
        <p>${activeMatch ? getMatchTitle(data, activeMatch) : 'Noch kein aktives Spiel'}</p>
        <div class="score-badge">${activeMatch ? getMatchScore(activeMatch) : '0:0'}</div>
      </div>
      <div class="section-block">
        <h4>Gruppen</h4>
        ${data.groups.length
          ? data.groups.map(group => `
              <div class="group">
                <strong>${group.name}</strong>
                <ul>${getGroupStandings(data, group.id).map(team => `<li>${team.name} - ${team.points} P, ${team.goals} Tore</li>`).join('')}</ul>
              </div>`).join('')
          : '<p>Noch keine Gruppen erstellt.</p>'}
      </div>
      <div class="section-block">
        <h4>Spielplan</h4>
        ${data.groups.length
          ? data.groups.map(group => `
              <div class="group">
                <strong>${group.name}</strong>
                <ul>${data.matches.filter(match => match.stage === 'group' && match.groupId === group.id).map(match => `<li>${getMatchTitle(data, match)} - ${getMatchScore(match)}${isMatchConfirmed(match) ? ' bestätigt' : ''}</li>`).join('') || '<li>Keine Spiele</li>'}</ul>
              </div>`).join('')
          : '<p>Nach der Gruppenbildung erscheinen hier die Partien.</p>'}
      </div>
    </div>`;
}

function renderTicker(data, targetId = 'tickerState'){
  const el = document.getElementById(targetId);
  if(!el) return;

  const active = data.matches.find(match => match.id === data.activeMatchId);
  const homeTeam = active ? getTeamById(data, active.homeTeamId)?.name : '';
  const awayTeam = active ? getTeamById(data, active.awayTeamId)?.name : '';

  el.innerHTML = `
    <div class="ticker-box">
      <p class="eyebrow">Aktives Spiel</p>
      <h3>${active ? getMatchTitle(data, active) : 'Kein Spiel gewählt'}</h3>
      <div class="ticker-score">
        <span>${homeTeam || 'Team A'}</span>
        <strong>${active ? getMatchScore(active) : '0:0'}</strong>
        <span>${awayTeam || 'Team B'}</span>
      </div>
      <p>${data.activeMatchNote || active?.note || (active?.status === 'confirmed' ? 'Bestätigt' : 'Noch nicht bestätigt')}</p>
      <small>Letzte Änderung: ${new Date(data.updatedAt).toLocaleString('de-DE')}</small>
    </div>`;
}

function renderAdminState(data){
  const el = document.getElementById('adminState');
  if(!el) return;

  el.innerHTML = `
    <div class="dashboard-grid">
      <div class="panel">
        <h4>Teams</h4>
        <ul>${data.teams.map(team => `<li>${team.name}</li>`).join('') || '<li>Keine Teams</li>'}</ul>
      </div>
      <div class="panel">
        <h4>Spiele</h4>
        ${data.matches.map(match => `
          <div class="match-row ${match.id === data.activeMatchId ? 'active' : ''}">
            <div>
              <strong>${getMatchTitle(data, match)}</strong>
              <p>${match.note || 'Kein Hinweis'} ${match.status === 'confirmed' ? ' - bestätigt' : ' - live'}</p>
            </div>
            <div class="match-meta">
              <span>${STAGE_LABELS[match.stage] || match.stage}</span>
              <strong>${getMatchScore(match)}</strong>
            </div>
          </div>`).join('') || '<p>Keine Spiele</p>'}
      </div>
    </div>`;
}

function renderMatchOptions(data){
  const select = document.getElementById('matchSelect');
  if(!select) return;

  select.innerHTML = data.matches.map(match => {
    const label = `${getMatchTitle(data, match)} (${getMatchScore(match)})`;
    return `<option value="${match.id}" ${match.id === data.activeMatchId ? 'selected' : ''}>${label}</option>`;
  }).join('');
}

function renderGoalControls(data){
  const el = document.getElementById('goalControls');
  if(!el) return;

  const active = data.matches.find(match => match.id === data.activeMatchId);
  if(!active){
    el.innerHTML = '<p>Kein aktives Spiel ausgewählt.</p>';
    return;
  }

  const homeTeam = getTeamById(data, active.homeTeamId);
  const awayTeam = getTeamById(data, active.awayTeamId);

  el.innerHTML = `
    <div class="goal-grid">
      <button type="button" data-goal-side="home" ${homeTeam ? '' : 'disabled'}>+ Tor ${homeTeam?.name || 'Team A'}</button>
      <button type="button" data-goal-side="away" ${awayTeam ? '' : 'disabled'}>+ Tor ${awayTeam?.name || 'Team B'}</button>
      <button type="button" data-action="confirm-match">Spiel bestätigen</button>
    </div>`;
}

function renderQualifiedTeams(data){
  const el = document.getElementById('qualifiedTeams');
  if(!el) return;

  if(!data.groups.length){
    el.innerHTML = '<p>Nach der Gruppenphase erscheinen hier die qualifizierten Teams.</p>';
    return;
  }

  const qualified = getQualifiedTeams(data, 2);
  el.innerHTML = qualified.length
    ? `<ul>${qualified.map(team => `<li>${team.name} (${team.points} P)</li>`).join('')}</ul>`
    : '<p>Noch keine qualifizierten Teams.</p>';
}

async function boot(){
  const data = await loadData();
  renderPublic(data);
  renderTicker(data, 'tickerState');
  renderTicker(data, 'tickerPage');
  renderMatchOptions(data);
  renderGoalControls(data);
  renderAdminState(data);
  renderGroupSchedule(data);
  renderQualifiedTeams(data);
}

boot();
setInterval(boot, 1500);
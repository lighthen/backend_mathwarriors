const WebSocket = require('ws');

const PORT = 8080;
const server = new WebSocket.Server({ port: PORT });

const clients = new Map();
const playerScores = new Map();
let clientIdCounter = 0;

console.log(`[WS] WebSocket server running on ws://localhost:${PORT}`);

function sendTo(client, data) {
  if (client.readyState === WebSocket.OPEN) {
    client.send(JSON.stringify(data));
  }
}

function sendToAll(data) {
  const message = JSON.stringify(data);
  clients.forEach((client) => {
    if (client.readyState === WebSocket.OPEN) {
      client.send(message);
    }
  });
}

/**
 * Check if the new total_points surpasses any other connected player.
 * Returns list of {client, username, oldPoints} that got beaten.
 */
function findBeatenPlayers(senderClientId, username, newPoints) {
  const beaten = [];
  clients.forEach((client, id) => {
    if (id === senderClientId) return;
    const entry = playerScores.get(id);
    if (entry && entry.points < newPoints) {
      beaten.push({ client, username: entry.username, oldPoints: entry.points });
    }
  });
  return beaten;
}

server.on('connection', (ws) => {
  const clientId = ++clientIdCounter;
  clients.set(clientId, ws);
  console.log(`[WS] Client #${clientId} connected. Total: ${clients.size}`);

  sendTo(ws, {
    type: 'connected',
    title: 'Terhubung',
    body: 'Terhubung ke server MathWarriors',
    data: { client_id: clientId },
  });

  sendToAll({
    type: 'user_online',
    title: 'Pengguna Online',
    body: 'Pemain baru telah bergabung!',
    data: { client_id: clientId },
  });

  ws.on('message', (raw) => {
    try {
      const data = JSON.parse(raw.toString());
      console.log(`[WS] Message from #${clientId}:`, data.type);

      switch (data.type) {
        case 'register':
          playerScores.set(clientId, {
            username: data.username,
            points: data.total_points || 0,
          });
          console.log(`[WS] #${clientId} registered as "${data.username}" (${data.total_points} pts)`);
          sendTo(ws, {
            type: 'registered',
            title: 'Registrasi Berhasil',
            body: `Terdaftar sebagai ${data.username}`,
          });
          break;

        case 'score_update':
          const username = data.username || 'Pemain';
          const newPoints = data.total_points || 0;

          playerScores.set(clientId, { username, points: newPoints });

          sendToAll({
            type: 'score_update',
            title: 'Perubahan Skor',
            body: `Skor ${username}: ${newPoints}`,
            data: { username, score: newPoints, client_id: clientId },
          });

          const beaten = findBeatenPlayers(clientId, username, newPoints);
          beaten.forEach(({ client, username: beatenUser, oldPoints }) => {
            console.log(`[WS] "${username}" melewati skor "${beatenUser}" (${oldPoints} -> ${newPoints})`);
            sendTo(client, {
              type: 'score_beaten',
              title: 'Skor Terlewati!',
              body: `${username} melewati skor kamu! Ayo main lagi untuk mengambil alih posisi!`,
              data: {
                beaten_by: username,
                beaten_by_points: newPoints,
                your_points: oldPoints,
              },
            });
          });
          break;

        case 'achievement':
          sendToAll({
            type: 'achievement',
            title: 'Pencapaian Baru!',
            body: `${username || 'Pemain'} mendapatkan: ${data.achievement}`,
            data: {
              username: data.username,
              achievement: data.achievement,
              client_id: clientId,
            },
          });
          break;

        default:
          sendTo(ws, {
            type: 'unknown',
            title: 'Unknown',
            body: 'Unknown message type',
          });
      }
    } catch (e) {
      console.log(`[WS] Invalid message from #${clientId}`);
    }
  });

  ws.on('close', () => {
    clients.delete(clientId);
    playerScores.delete(clientId);
    console.log(`[WS] Client #${clientId} disconnected. Total: ${clients.size}`);

    sendToAll({
      type: 'user_offline',
      title: 'Pengguna Offline',
      body: 'Seorang pemain telah keluar',
      data: { client_id: clientId },
    });
  });

  ws.on('error', (err) => {
    console.error(`[WS] Error #${clientId}:`, err.message);
    clients.delete(clientId);
    playerScores.delete(clientId);
  });
});

server.on('error', (err) => {
  console.error('[WS] Server error:', err.message);
});

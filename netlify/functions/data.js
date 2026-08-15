const admin = require('firebase-admin');

// Firebase initialisieren (nutzt Umgebungsvariablen)
const serviceAccount = {
  type: process.env.FIREBASE_TYPE,
  project_id: process.env.FIREBASE_PROJECT_ID,
  private_key_id: process.env.FIREBASE_PRIVATE_KEY_ID,
  private_key: process.env.FIREBASE_PRIVATE_KEY?.replace(/\\n/g, '\n'),
  client_email: process.env.FIREBASE_CLIENT_EMAIL,
  client_id: process.env.FIREBASE_CLIENT_ID,
  auth_uri: process.env.FIREBASE_AUTH_URI,
  token_uri: process.env.FIREBASE_TOKEN_URI,
  auth_provider_x509_cert_url: process.env.FIREBASE_AUTH_PROVIDER_CERT_URL,
  client_x509_cert_url: process.env.FIREBASE_CLIENT_CERT_URL,
};

if (!admin.apps.length) {
  admin.initializeApp({
    credential: admin.credential.cert(serviceAccount),
    databaseURL: process.env.FIREBASE_DATABASE_URL
  });
}

const db = admin.database();

exports.handler = async (event, context) => {
  // CORS Headers
  const headers = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Content-Type': 'application/json'
  };

  // OPTIONS request für CORS
  if (event.httpMethod === 'OPTIONS') {
    return {
      statusCode: 200,
      headers,
      body: ''
    };
  }

  try {
    // GET - Daten laden
    if (event.httpMethod === 'GET') {
      const snapshot = await db.ref('tournament').once('value');
      const data = snapshot.val();
      
      return {
        statusCode: 200,
        headers,
        body: JSON.stringify(data || {
          tournamentName: 'Mein Turnier',
          teams: [],
          groups: [],
          matches: [],
          activeMatchId: '',
          activeMatchNote: '',
          updatedAt: new Date().toISOString()
        })
      };
    }

    // POST - Daten speichern (mit Admin-Authentifizierung)
    if (event.httpMethod === 'POST') {
      const body = JSON.parse(event.body);
      const { data, adminPassword } = body;

      // Passwort überprüfen
      const expectedHash = '937e8d5fbb48bd4949536cd65b8d35c426b80d2f830c5c308e2cdec422ae2244';
      
      // SHA256 in Node.js
      const crypto = require('crypto');
      const actualHash = crypto.createHash('sha256').update(adminPassword || '').digest('hex');

      if (actualHash !== expectedHash) {
        return {
          statusCode: 401,
          headers,
          body: JSON.stringify({ error: 'Unauthorized' })
        };
      }

      // Daten speichern
      await db.ref('tournament').set({
        ...data,
        updatedAt: new Date().toISOString()
      });

      return {
        statusCode: 200,
        headers,
        body: JSON.stringify({ success: true })
      };
    }

    return {
      statusCode: 405,
      headers,
      body: JSON.stringify({ error: 'Method not allowed' })
    };

  } catch (error) {
    console.error(error);
    return {
      statusCode: 500,
      headers,
      body: JSON.stringify({ error: error.message })
    };
  }
};

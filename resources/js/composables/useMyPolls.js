const KEY = 'sv_my_polls'
const LEGACY_KEY = 'sv_last_manage_token'
const MAX_ENTRIES = 12

/**
 * Merkt sich erstellte Umfragen/Events auf dem Gerät. Ersetzt bewusst kein
 * Konto – der Verwaltungs-Link bleibt der einzige echte Schlüssel, das hier
 * ist nur Bequemlichkeit auf der Startseite.
 */
function write(list) {
  try {
    localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX_ENTRIES)))
  } catch (e) {
    // LocalStorage voll/gesperrt – Liste ist nur Komfort
  }
}

export function readMyPolls() {
  try {
    const raw = localStorage.getItem(KEY)
    let list = raw ? JSON.parse(raw) : []
    if (!Array.isArray(list)) list = []

    // Alten Einzel-Link übernehmen, damit niemand seinen Zugang verliert
    const legacy = localStorage.getItem(LEGACY_KEY)
    if (legacy) {
      if (!list.some((p) => p.manage_token === legacy)) {
        list.push({ manage_token: legacy, public_token: null, title: null, updated_at: null })
      }
      write(list)
      localStorage.removeItem(LEGACY_KEY)
    }

    return list
  } catch (e) {
    return []
  }
}

export function rememberPoll({ manage_token, public_token, title }) {
  const list = readMyPolls().filter((p) => p.manage_token !== manage_token)
  list.unshift({ manage_token, public_token, title, updated_at: new Date().toISOString() })
  write(list)
}

export function updatePollTitle(manage_token, title) {
  const list = readMyPolls()
  const entry = list.find((p) => p.manage_token === manage_token)
  if (!entry || entry.title === title) return
  entry.title = title
  write(list)
}

export function forgetPoll(manage_token) {
  write(readMyPolls().filter((p) => p.manage_token !== manage_token))
}

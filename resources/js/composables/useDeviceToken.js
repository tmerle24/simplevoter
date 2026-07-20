/**
 * Erzeugt/liest einen pro-Gerät-Token aus LocalStorage. Wird für
 * voter_token (Votes) und author_token (Fragen-Rate-Limiting) genutzt.
 * Kein Login, kein Cookie-Consent nötig – reine Client-Identifikation
 * analog zum Wisherful-"Auth ohne Auth"-Muster.
 */
export function useDeviceToken(storageKey) {
  let token = localStorage.getItem(storageKey)

  if (!token) {
    token = crypto.randomUUID().replace(/-/g, '')
    localStorage.setItem(storageKey, token)
  }

  return token
}

export function useVoterToken(publicToken) {
  return useDeviceToken(`sv_voter_${publicToken}`)
}

export function useAuthorToken(publicToken) {
  return useDeviceToken(`sv_author_${publicToken}`)
}

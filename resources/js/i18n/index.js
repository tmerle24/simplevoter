import { createI18n } from 'vue-i18n'
import de from './locales/de.json'
import en from './locales/en.json'
import fr from './locales/fr.json'
import es from './locales/es.json'
import nl from './locales/nl.json'

const SUPPORTED_LOCALES = ['de', 'en', 'fr', 'es', 'nl']
const STORAGE_KEY = 'simplevoter_locale'

/**
 * Spracherkennung (Spec Abschnitt 12.2):
 * 1. Gespeicherte Auswahl aus LocalStorage hat Vorrang
 * 2. sonst navigator.language auf unterstützte Codes mappen
 * 3. Fallback: 'en'
 */
function detectLocale() {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored && SUPPORTED_LOCALES.includes(stored)) {
    return stored
  }

  const browserLang = (navigator.language || 'en').slice(0, 2).toLowerCase()
  return SUPPORTED_LOCALES.includes(browserLang) ? browserLang : 'en'
}

export function setLocale(locale) {
  if (!SUPPORTED_LOCALES.includes(locale)) return
  i18n.global.locale.value = locale
  localStorage.setItem(STORAGE_KEY, locale)
  document.documentElement.setAttribute('lang', locale)
}

export const i18n = createI18n({
  legacy: false,
  locale: detectLocale(),
  fallbackLocale: 'en',
  messages: { de, en, fr, es, nl },
})

export { SUPPORTED_LOCALES }

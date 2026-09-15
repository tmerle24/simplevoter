import { watchEffect, onUnmounted } from 'vue'

export const DEFAULT_PRIMARY = '#2b2c30'
export const DEFAULT_ACCENT = '#bb3245'

const VARS = ['--sv-primary', '--sv-on-primary', '--color-sv-accent', '--color-sv-accent-light', '--sv-on-accent']

// Button-Text hell oder dunkel je nach Hintergrund (WCAG relative luminance)
export function readableTextColor(hex) {
  const [r, g, b] = [1, 3, 5].map((i) => {
    const c = parseInt(hex.slice(i, i + 2), 16) / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  })
  const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b
  return luminance > 0.4 ? DEFAULT_PRIMARY : '#ffffff'
}

// CSS-Variablen für Branding; ungesetzte Farben → SimpleVoter-Defaults
export function brandingVars(branding) {
  const primary = branding?.primary_color || DEFAULT_PRIMARY
  const accent = branding?.accent_color || DEFAULT_ACCENT
  return {
    '--sv-primary': primary,
    '--sv-on-primary': readableTextColor(primary),
    '--color-sv-accent': accent,
    '--color-sv-accent-light': `color-mix(in srgb, ${accent} 12%, white)`,
    '--sv-on-accent': readableTextColor(accent),
  }
}

// Setzt Variablen auf <html>, damit auch teleportierte Modals sie erben
export function useBranding(getBranding) {
  const root = document.documentElement

  watchEffect(() => {
    Object.entries(brandingVars(getBranding())).forEach(([key, value]) => root.style.setProperty(key, value))
  })

  onUnmounted(() => VARS.forEach((key) => root.style.removeProperty(key)))
}

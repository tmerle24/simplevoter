<script setup>
import { ref, computed } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

const { t } = useI18n()

const question = ref('')
const options = ref(['', ''])
const website = ref('') // Honeypot – bleibt für Menschen unsichtbar (Abschnitt 10)
const submitting = ref(false)
const error = ref('')

const lastManageToken = localStorage.getItem('sv_last_manage_token')

const features = [
  {
    titleKey: 'landing.features.zeroFrictionTitle',
    textKey: 'landing.features.zeroFrictionText',
    icon: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z" fill="#ffffff" stroke="#ffffff" stroke-width="1.3" stroke-linejoin="round" opacity="0.95"/>
    </svg>`,
  },
  {
    titleKey: 'landing.features.liveTitle',
    textKey: 'landing.features.liveText',
    icon: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="3.5" y="12" width="4" height="8.5" rx="1" fill="#ffffff" opacity="0.4"/>
      <rect x="10" y="7" width="4" height="13.5" rx="1" fill="#ffffff" opacity="0.7"/>
      <rect x="16.5" y="3" width="4" height="17.5" rx="1" fill="#ffffff"/>
    </svg>`,
  },
  {
    titleKey: 'landing.features.qaTitle',
    textKey: 'landing.features.qaText',
    icon: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9.5L5 20v-4H6a2 2 0 0 1-2-2V6Z" fill="none" stroke="#ffffff" stroke-width="1.3" stroke-linejoin="round" opacity="0.9"/>
      <circle cx="8.5" cy="10" r="1.4" fill="#ffffff"/>
      <circle cx="13" cy="10" r="1.4" fill="#ffffff"/>
      <circle cx="17.5" cy="10" r="1.4" fill="#ffffff"/>
    </svg>`,
  },
  {
    titleKey: 'landing.features.shareTitle',
    textKey: 'landing.features.shareText',
    icon: `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="3" y="3" width="7.5" height="7.5" rx="1.6" fill="#ffffff"/>
      <rect x="5.3" y="5.3" width="2.9" height="2.9" fill="var(--color-sv-dark)"/>
      <rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6" fill="#ffffff" opacity="0.35"/>
      <rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6" fill="#ffffff" opacity="0.6"/>
      <rect x="13.5" y="13.5" width="3.2" height="3.2" rx="0.8" fill="#ffffff" opacity="0.6"/>
      <rect x="17.8" y="13.5" width="3.2" height="3.2" rx="0.8" fill="#ffffff"/>
      <rect x="13.5" y="17.8" width="3.2" height="3.2" rx="0.8" fill="#ffffff"/>
    </svg>`,
  },
]

const canSubmit = computed(() => {
  const filled = options.value.map((o) => o.trim()).filter(Boolean)
  const optionsValid = filled.length === 0 || filled.length >= 2
  return question.value.trim().length > 0 && optionsValid && !submitting.value
})

function addOption() {
  if (options.value.length >= 20) return
  options.value.push('')
}

function removeOption(index) {
  if (options.value.length <= 2) return
  options.value.splice(index, 1)
}

async function submit() {
  if (!canSubmit.value) return
  submitting.value = true
  error.value = ''

  try {
    const { data } = await axios.post('/polls', {
      question: question.value.trim(),
      options: options.value.map((o) => o.trim()).filter(Boolean),
      website: website.value,
    })

    // Zero-Friction-Flow (Spec Abschnitt 2): manage_token lokal merken,
    // dann direkt zur Verwaltungsseite weiterleiten.
    localStorage.setItem('sv_last_manage_token', data.manage_token)
    router.visit(`/p/${data.manage_token}/edit`)
  } catch (e) {
    error.value = t('common.error')
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <Head title="SimpleVoter – Umfragen in Sekunden erstellt" />

    <header class="flex items-center justify-between px-6 py-3 max-w-2xl w-full mx-auto">
      <img src="/images/logo-simplevoter.png" alt="SimpleVoter" class="w-44 h-auto" />
      <LanguageSwitcher />
    </header>

    <main class="flex-1 flex items-start justify-center px-6 pt-6 pb-16">
      <div class="w-full max-w-xl">
        <h1 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--color-sv-dark)] mb-8 leading-tight">
          {{ t('landing.title') }}
        </h1>

        <form @submit.prevent="submit" class="space-y-6">
          <div>
            <input
              v-model="question"
              type="text"
              :placeholder="t('landing.questionPlaceholder')"
              maxlength="500"
              class="w-full text-lg font-display px-4 py-3 rounded-xl bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)] placeholder:text-[var(--color-sv-gray)]"
            />
          </div>

          <div>
            <p class="text-sm font-medium text-[var(--color-sv-gray)] mb-1">
              {{ t('landing.optionsLabel') }}
            </p>
            <p class="text-xs text-[var(--color-sv-gray)] mb-2">
              {{ t('landing.optionsHint') }}
            </p>
            <div class="space-y-2">
              <div
                v-for="(opt, i) in options"
                :key="i"
                class="flex items-center gap-2"
              >
                <input
                  v-model="options[i]"
                  type="text"
                  :placeholder="t('landing.optionPlaceholder', { n: i + 1 })"
                  maxlength="200"
                  class="flex-1 px-3 py-2 rounded-lg bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)] placeholder:text-[var(--color-sv-gray)]"
                />
                <button
                  v-if="options.length > 2"
                  type="button"
                  @click="removeOption(i)"
                  class="text-[var(--color-sv-gray)] hover:text-[var(--color-sv-accent)] w-8 h-8 flex items-center justify-center rounded-lg"
                  aria-label="Option entfernen"
                >
                  ✕
                </button>
              </div>
            </div>
            <button
              type="button"
              @click="addOption"
              class="mt-3 text-sm font-medium text-[var(--color-sv-accent)] hover:opacity-80"
            >
              {{ t('landing.addOption') }}
            </button>
          </div>

          <!-- Honeypot: für Menschen unsichtbar, Bots füllen es oft aus -->
          <input
            v-model="website"
            type="text"
            name="website"
            tabindex="-1"
            autocomplete="off"
            class="absolute -left-[9999px] w-px h-px opacity-0"
            aria-hidden="true"
          />

          <p v-if="error" class="text-sm text-[var(--color-sv-accent)]">{{ error }}</p>

          <button
            type="submit"
            :disabled="!canSubmit"
            class="w-full sm:w-auto px-6 py-3 rounded-xl bg-[var(--color-sv-dark)] text-white font-medium hover:bg-[var(--color-sv-accent)] transition-colors disabled:opacity-40 disabled:hover:bg-[var(--color-sv-dark)]"
          >
            {{ submitting ? t('landing.submitting') : t('landing.submit') }}
          </button>
        </form>

        <p v-if="lastManageToken" class="mt-8 text-sm text-[var(--color-sv-gray)]">
          <a :href="`/p/${lastManageToken}/edit`" class="underline hover:text-[var(--color-sv-accent)]">
            → {{ t('manage.manageTitle') }}: {{ t('manage.open') }}
          </a>
        </p>
      </div>
    </main>

    <div class="bg-[var(--color-sv-surface)]">
      <section class="max-w-2xl w-full mx-auto px-6 py-16">
        <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-6">
          {{ t('landing.features.title') }}
        </h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div
            v-for="feature in features"
            :key="feature.titleKey"
            class="p-5 rounded-2xl bg-[var(--color-sv-bg)] border border-[var(--color-sv-gray-light)]"
          >
            <span
              class="w-11 h-11 rounded-xl flex items-center justify-center p-2.5"
              style="background: var(--color-sv-dark)"
              v-html="feature.icon"
            />
            <h3 class="font-display font-semibold text-base mt-3 mb-1">{{ t(feature.titleKey) }}</h3>
            <p class="text-sm text-[var(--color-sv-gray)] leading-relaxed">{{ t(feature.textKey) }}</p>
          </div>
        </div>
      </section>

      <Footer />
    </div>
  </div>
</template>

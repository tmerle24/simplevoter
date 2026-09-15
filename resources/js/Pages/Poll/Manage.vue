<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import { useI18n } from 'vue-i18n'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import { brandingVars, DEFAULT_PRIMARY, DEFAULT_ACCENT } from '@/composables/useBranding'

const props = defineProps({
  poll: { type: Object, required: true },
})

const { t, locale } = useI18n()

const poll = ref(props.poll)
const qrDataUrl = ref('')
const copyState = ref('idle') // idle | copied
const savingSettings = ref(false)
const settingsError = ref('')
const newOptionLabel = ref('')
const pollTimer = ref(null)
const isEditingField = ref(false)
const questionTextarea = ref(null)
const descriptionTextarea = ref(null)

function autoGrow(el) {
  if (!el) return
  el.style.height = 'auto'
  el.style.height = `${el.scrollHeight}px`
}

function autoGrowQuestion(event) {
  autoGrow(event?.target ?? questionTextarea.value)
}

function autoGrowDescription(event) {
  autoGrow(event?.target ?? descriptionTextarea.value)
}
const resetModalOpen = ref(false)
const resettingPoll = ref(false)
const manageLinkEmail = ref('')
const sendingManageLinkEmail = ref(false)
const manageLinkEmailStatus = ref('') // '' | 'sent' | 'error'

// Event / Umfragen-Box
const dragSourceId = ref(null)
const dragOverId = ref(null)

function onDragStart(pid) {
  dragSourceId.value = pid
}

function onDragOver(e, pid) {
  e.preventDefault()
  dragOverId.value = pid
}

function onDrop(pid) {
  if (dragSourceId.value === null || dragSourceId.value === pid) return
  const polls = [...poll.value.event.polls]
  const fromIdx = polls.findIndex((p) => p.id === dragSourceId.value)
  const toIdx = polls.findIndex((p) => p.id === pid)
  const [moved] = polls.splice(fromIdx, 1)
  polls.splice(toIdx, 0, moved)
  poll.value = { ...poll.value, event: { ...poll.value.event, polls } }
  axios
    .post(`/p/${poll.value.manage_token}/edit/polls/reorder`, { order: polls.map((p) => p.id) })
    .catch(() => refresh())
}

function onDragEnd() {
  dragSourceId.value = null
  dragOverId.value = null
}

const addPollModalOpen = ref(false)
const newPollQuestion = ref('')
const newPollDescription = ref('')
const addingPoll = ref(false)
const editingEventName = ref(false)
const eventNameInput = ref('')
const savingEventName = ref(false)
const pollActionTarget = ref(null) // { pid, action: 'detach' | 'delete' }
const pollActionPending = ref(false)

// Branding-Modal
const brandingModalOpen = ref(false)
const savingBranding = ref(false)
const brandingError = ref('')
const brandingLogoInput = ref(null)
const brandingForm = ref({ primary: DEFAULT_PRIMARY, accent: DEFAULT_ACCENT, logoFile: null, logoPreview: null, removeLogo: false })
const HEX_COLOR = /^#[0-9a-fA-F]{6}$/
const MAX_LOGO_BYTES = 2 * 1024 * 1024

const hasCustomBranding = computed(() => {
  const b = poll.value.branding
  return !!(b?.logo_url || b?.primary_color || b?.accent_color)
})

const brandingPreviewStyle = computed(() => brandingVars({
  primary_color: HEX_COLOR.test(brandingForm.value.primary) ? brandingForm.value.primary : null,
  accent_color: HEX_COLOR.test(brandingForm.value.accent) ? brandingForm.value.accent : null,
}))

function openBrandingModal() {
  const b = poll.value.branding ?? {}
  brandingForm.value = {
    primary: b.primary_color || DEFAULT_PRIMARY,
    accent: b.accent_color || DEFAULT_ACCENT,
    logoFile: null,
    logoPreview: b.logo_url || null,
    removeLogo: false,
  }
  brandingError.value = ''
  brandingModalOpen.value = true
}

function selectBrandingLogo(file) {
  if (!file) return
  if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
    brandingError.value = t('manage.brandingLogoFormats')
    return
  }
  if (file.size > MAX_LOGO_BYTES) {
    brandingError.value = t('manage.brandingLogoTooLarge')
    return
  }
  brandingError.value = ''
  brandingForm.value = { ...brandingForm.value, logoFile: file, logoPreview: URL.createObjectURL(file), removeLogo: false }
}

function removeBrandingLogo() {
  brandingForm.value = { ...brandingForm.value, logoFile: null, logoPreview: null, removeLogo: true }
  if (brandingLogoInput.value) brandingLogoInput.value.value = ''
}

function resetBrandingForm() {
  removeBrandingLogo()
  brandingForm.value = { ...brandingForm.value, primary: DEFAULT_PRIMARY, accent: DEFAULT_ACCENT }
}

async function saveBranding() {
  const { primary, accent, logoFile, removeLogo } = brandingForm.value
  if (!HEX_COLOR.test(primary) || !HEX_COLOR.test(accent)) {
    brandingError.value = t('manage.brandingInvalidColor')
    return
  }

  const form = new FormData()
  // Default-Farbe → leer, damit spätere Default-Änderungen greifen
  form.append('primary_color', primary.toLowerCase() === DEFAULT_PRIMARY ? '' : primary)
  form.append('accent_color', accent.toLowerCase() === DEFAULT_ACCENT ? '' : accent)
  if (logoFile) form.append('logo', logoFile)
  if (removeLogo) form.append('remove_logo', '1')

  savingBranding.value = true
  brandingError.value = ''
  try {
    const { data } = await axios.post(`/p/${poll.value.manage_token}/edit/branding`, form)
    poll.value = data
    brandingModalOpen.value = false
  } catch (e) {
    console.error('Branding konnte nicht gespeichert werden:', e.response?.status, e.response?.data)
    brandingError.value = e.response?.status === 422
      ? Object.values(e.response.data.errors ?? {})[0]?.[0] || t('common.error')
      : t('common.error')
  } finally {
    savingBranding.value = false
  }
}

const publicUrl = computed(() => `${window.location.origin}/w/${poll.value.public_token}`)
const manageUrl = computed(() => `${window.location.origin}/p/${poll.value.manage_token}/edit`)

localStorage.setItem('sv_last_manage_token', poll.value.manage_token)

async function refresh() {
  try {
    const { data } = await axios.get(`/p/${poll.value.manage_token}/edit/data`)
    poll.value = data
    await nextTick()
    autoGrowQuestion()
    autoGrowDescription()
    autoGrowAllOptions()
  } catch (e) {
    // Live-Refresh-Fehler bewusst still ignorieren, nächster Tick versucht's erneut
  }
}

async function saveSettings(patch) {
  const previous = poll.value
  poll.value = { ...poll.value, ...patch }
  savingSettings.value = true
  settingsError.value = ''
  try {
    const { data } = await axios.patch(`/p/${poll.value.manage_token}/edit`, patch)
    poll.value = data
    await nextTick()
    autoGrowQuestion()
    autoGrowDescription()
    autoGrowAllOptions()
  } catch (e) {
    poll.value = previous // Bei Fehler: alten Zustand wiederherstellen
    console.error('Einstellung konnte nicht gespeichert werden:', e.response?.status, e.response?.data)
    settingsError.value = e.response?.data?.message || t('common.error')
  } finally {
    savingSettings.value = false
  }
}

async function addOption() {
  const label = newOptionLabel.value.trim()
  if (!label) return
  await axios.post(`/p/${poll.value.manage_token}/edit/options`, { label })
  newOptionLabel.value = ''
  await refresh()
}

async function updateOptionLabel(option, label) {
  if (!label.trim() || label === option.label) return
  await axios.patch(`/p/${poll.value.manage_token}/edit/options/${option.id}`, { label: label.trim() })
  await refresh()
}

async function deleteOption(option) {
  if (poll.value.options.length <= 2) return
  await axios.delete(`/p/${poll.value.manage_token}/edit/options/${option.id}`)
  await refresh()
}

async function moveOption(index, direction) {
  const order = poll.value.options.map((o) => o.id)
  const target = index + direction
  if (target < 0 || target >= order.length) return
  ;[order[index], order[target]] = [order[target], order[index]]
  await axios.post(`/p/${poll.value.manage_token}/edit/reorder`, { order })
  await refresh()
}

async function copyLink(url) {
  await navigator.clipboard.writeText(url)
  copyState.value = 'copied'
  setTimeout(() => (copyState.value = 'idle'), 1500)
}

function downloadQr() {
  const link = document.createElement('a')
  link.href = qrDataUrl.value
  link.download = 'simplevoter-qr.png'
  link.click()
}

function autoGrowAllOptions() {
  document.querySelectorAll('.option-label-textarea').forEach(autoGrow)
}

onMounted(async () => {
  await nextTick()
  autoGrowQuestion()
  autoGrowDescription()
  autoGrowAllOptions()

  qrDataUrl.value = await QRCode.toDataURL(publicUrl.value, {
    width: 320,
    margin: 1,
    color: { dark: '#2b2c30', light: '#ffffff' },
  })

  pollTimer.value = setInterval(() => {
    // Während ein Eingabefeld fokussiert ist, kein Live-Refresh –
    // sonst überschreibt refresh() ungespeicherte Eingaben mitten im Tippen.
    if (!isEditingField.value) refresh()
  }, 6000)
})

onUnmounted(() => {
  clearInterval(pollTimer.value)
})

async function toggleActive() {
  await saveSettings({ is_active: !poll.value.is_active })
}

async function sendManageLinkEmail() {
  if (!manageLinkEmail.value.trim() || sendingManageLinkEmail.value) return
  sendingManageLinkEmail.value = true
  manageLinkEmailStatus.value = ''
  try {
    await axios.post(`/p/${poll.value.manage_token}/edit/email`, { email: manageLinkEmail.value.trim() })
    manageLinkEmailStatus.value = 'sent'
    manageLinkEmail.value = ''
  } catch (e) {
    console.error('Verwaltungs-Link-Mail fehlgeschlagen:', e.response?.status, e.response?.data)
    manageLinkEmailStatus.value = 'error'
  } finally {
    sendingManageLinkEmail.value = false
  }
}

async function confirmReset() {
  resettingPoll.value = true
  try {
    const { data } = await axios.post(`/p/${poll.value.manage_token}/edit/reset`)
    poll.value = data
    resetModalOpen.value = false
  } finally {
    resettingPoll.value = false
  }
}

function downloadResults() {
  window.print()
}

async function submitAddPoll() {
  if (!newPollQuestion.value.trim() || addingPoll.value) return
  addingPoll.value = true
  try {
    const { data } = await axios.post(`/p/${poll.value.manage_token}/edit/polls`, {
      question: newPollQuestion.value.trim(),
      description: newPollDescription.value.trim() || undefined,
    })
    poll.value = data
    newPollQuestion.value = ''
    newPollDescription.value = ''
    addPollModalOpen.value = false
  } finally {
    addingPoll.value = false
  }
}

async function activatePoll(pid) {
  try {
    const { data } = await axios.post(`/p/${poll.value.manage_token}/edit/polls/${pid}/activate`)
    poll.value = data
    await nextTick()
    autoGrowQuestion()
    autoGrowDescription()
    autoGrowAllOptions()
  } catch (e) {
    console.error('Poll-Wechsel fehlgeschlagen:', e.response?.status)
  }
}

async function confirmPollAction() {
  if (!pollActionTarget.value || pollActionPending.value) return
  pollActionPending.value = true
  const { pid, action } = pollActionTarget.value
  try {
    const { data } = action === 'delete'
      ? await axios.delete(`/p/${poll.value.manage_token}/edit/polls/${pid}`)
      : await axios.post(`/p/${poll.value.manage_token}/edit/polls/${pid}/detach`)
    if (data.redirect) {
      window.location.href = data.redirect
      return
    }
    poll.value = data
    pollActionTarget.value = null
  } catch (e) {
    console.error('Poll-Aktion fehlgeschlagen:', e.response?.status)
  } finally {
    pollActionPending.value = false
  }
}

async function saveEventName() {
  savingEventName.value = true
  try {
    const { data } = await axios.patch(`/p/${poll.value.manage_token}/edit/event`, {
      name: eventNameInput.value.trim() || null,
    })
    poll.value = data
    editingEventName.value = false
  } finally {
    savingEventName.value = false
  }
}

function maxVotes(options) {
  return Math.max(1, ...options.map((o) => o.vote_count || 0))
}

const totalVotes = computed(() =>
  poll.value.options.reduce((sum, o) => sum + (o.vote_count || 0), 0)
)

function percent(option) {
  if (!totalVotes.value) return 0
  return Math.round((option.vote_count / totalVotes.value) * 100)
}

const exportDate = computed(() =>
  new Date().toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' })
)
</script>

<template>
  <div class="min-h-screen overflow-x-hidden">
    <Head :title="`${poll.question} – Verwalten – SimpleVoter`">
      <meta name="robots" content="noindex, nofollow" />
    </Head>

    <header class="flex items-center justify-between px-6 py-3 max-w-4xl w-full mx-auto">
      <img src="/images/logo-simplevoter.png" alt="SimpleVoter" class="w-44 h-auto" />
      <LanguageSwitcher />
    </header>

    <p v-if="settingsError" class="max-w-4xl mx-auto px-6 text-sm text-[var(--color-sv-accent)] mb-4">
      {{ settingsError }}
    </p>

    <main class="max-w-4xl mx-auto px-6 pb-16 grid gap-6 lg:grid-cols-[1.2fr_1fr] min-w-0">
      <!-- Linke Spalte: Beschreibung, Ergebnisse, Fragen -->
      <div class="space-y-6 min-w-0">
        <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6 overflow-hidden">
          <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-3">
            {{ t('manage.descriptionSection') }}
          </h2>
          <p v-if="poll.event?.name" class="text-sm text-[var(--color-sv-accent)] mb-1">{{ poll.event.name }}</p>
          <textarea
            ref="questionTextarea"
            :value="poll.question"
            @input="autoGrowQuestion"
            @change="saveSettings({ question: $event.target.value })"
            @focus="isEditingField = true"
            @blur="isEditingField = false"
            rows="1"
            class="block w-full max-w-full min-w-0 box-border font-display font-semibold text-2xl mb-2 py-1 rounded-lg resize-none overflow-hidden focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)]"
          />
          <textarea
            ref="descriptionTextarea"
            :value="poll.description"
            @input="autoGrowDescription"
            @change="saveSettings({ description: $event.target.value })"
            @focus="isEditingField = true"
            @blur="isEditingField = false"
            :placeholder="t('manage.descriptionLabel')"
            rows="2"
            class="block w-full max-w-full min-w-0 box-border text-sm text-[var(--color-sv-gray)] py-1 rounded-lg resize-none overflow-hidden focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)]"
          />
        </section>

        <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)]">
              {{ t('manage.resultsTitle') }}
            </h2>
            <span class="flex items-center gap-1.5 text-xs font-medium text-[var(--color-sv-accent)]">
              <span class="sv-live-dot w-1.5 h-1.5 rounded-full bg-[var(--color-sv-accent)]" />
              {{ t('manage.live') }}
            </span>
          </div>

          <div class="space-y-3">
            <div v-for="(option, i) in poll.options" :key="option.id" class="group">
              <div class="flex items-start justify-between text-sm mb-1 min-w-0 gap-2">
                <textarea
                  :value="option.label"
                  @input="autoGrow($event.target)"
                  @change="updateOptionLabel(option, $event.target.value)"
                  @focus="isEditingField = true"
                  @blur="isEditingField = false"
                  rows="1"
                  class="option-label-textarea font-medium bg-transparent flex-1 min-w-0 box-border resize-none overflow-hidden focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)] rounded px-1"
                />
                <span class="font-mono-num text-[var(--color-sv-gray)] shrink-0">{{ option.vote_count }} {{ t('manage.votes') }}</span>
              </div>
              <div class="h-2 rounded-full bg-[var(--color-sv-gray-light)] overflow-hidden">
                <div
                  class="h-full bg-[var(--color-sv-accent)] transition-all duration-500 ease-out rounded-full"
                  :style="{ width: maxVotes(poll.options) ? `${(option.vote_count / maxVotes(poll.options)) * 100}%` : '0%' }"
                />
              </div>
              <div class="flex gap-2 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button type="button" @click="moveOption(i, -1)" class="text-xs text-[var(--color-sv-gray)] hover:text-[var(--color-sv-accent)]">↑</button>
                <button type="button" @click="moveOption(i, 1)" class="text-xs text-[var(--color-sv-gray)] hover:text-[var(--color-sv-accent)]">↓</button>
                <button
                  v-if="poll.options.length > 2"
                  type="button"
                  @click="deleteOption(option)"
                  class="text-xs text-[var(--color-sv-gray)] hover:text-[var(--color-sv-accent)]"
                >
                  {{ t('manage.removeOption') }}
                </button>
              </div>
            </div>
          </div>

          <div class="flex gap-2 mt-4">
            <input
              v-model="newOptionLabel"
              @keyup.enter="addOption"
              :placeholder="t('manage.addOption')"
              class="flex-1 text-sm px-3 py-2 rounded-lg border border-[var(--color-sv-gray-light)] focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)]"
            />
            <button
              type="button"
              @click="addOption"
              class="px-3 py-2 rounded-lg bg-[var(--color-sv-dark)] text-white text-sm hover:bg-[var(--color-sv-accent)]"
            >
              +
            </button>
          </div>
        </section>

        <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
          <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-4">
            {{ t('manage.questionsFromParticipants') }}
          </h2>
          <p v-if="!poll.questions.length" class="text-sm text-[var(--color-sv-gray)]">
            {{ t('manage.noQuestionsYet') }}
          </p>
          <ul class="space-y-3">
            <li v-for="q in poll.questions" :key="q.id" class="border-b border-[var(--color-sv-gray-light)] pb-3 last:border-0">
              <p class="text-sm break-words">{{ q.content }}</p>
              <p v-if="q.author_name" class="text-xs text-[var(--color-sv-gray)] mt-1">— {{ q.author_name }}</p>
            </li>
          </ul>
        </section>
      </div>

      <!-- Rechte Spalte: Teilen, Verwalten, Einstellungen -->
      <div class="space-y-6 min-w-0">
        <section class="bg-[var(--color-sv-dark)] text-white rounded-2xl p-6">
          <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-4">
            {{ t('manage.shareTitle') }}
          </h2>
          <div class="bg-white rounded-xl p-3 mb-4 flex justify-center">
            <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR Code" class="w-40 h-40" />
          </div>
          <div class="flex gap-2 mb-2">
            <a :href="`/w/${poll.public_token}`" target="_blank" class="flex-1 text-center text-sm py-2 rounded-lg border border-white/20 hover:bg-white/10">
              {{ t('manage.preview') }}
            </a>
            <button type="button" @click="copyLink(publicUrl)" class="flex-1 text-sm py-2 rounded-lg bg-[var(--color-sv-accent)] hover:opacity-90">
              {{ copyState === 'copied' ? t('manage.copied') : t('manage.copyLink') }}
            </button>
          </div>
          <button type="button" @click="downloadQr" class="w-full text-sm py-2 rounded-lg border border-white/20 hover:bg-white/10">
            {{ t('manage.qrDownload') }}
          </button>
        </section>

        <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
          <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-3">
            {{ t('manage.manageTitle') }}
          </h2>
          <button type="button" @click="copyLink(manageUrl)" class="w-full text-sm py-2 rounded-lg border border-[var(--color-sv-gray-light)] hover:border-[var(--color-sv-accent)]">
            {{ copyState === 'copied' ? t('manage.copied') : t('manage.copyLink') }}
          </button>
          <p class="text-xs text-[var(--color-sv-gray)] mt-2 mb-4">
            {{ t('manage.manageLinkWarning') }}
          </p>

          <form @submit.prevent="sendManageLinkEmail" class="flex gap-2">
            <input
              v-model="manageLinkEmail"
              type="email"
              :placeholder="t('manage.emailPlaceholder')"
              class="flex-1 min-w-0 text-sm px-3 py-2 rounded-lg border border-[var(--color-sv-gray-light)] focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)]"
            />
            <button
              type="submit"
              :disabled="!manageLinkEmail.trim() || sendingManageLinkEmail"
              class="shrink-0 text-sm px-4 py-2 rounded-lg bg-[var(--color-sv-dark)] text-white hover:bg-[var(--color-sv-accent)] disabled:opacity-40 flex items-center gap-1.5"
            >
              <svg v-if="sendingManageLinkEmail" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              {{ t('manage.emailSend') }}
            </button>
          </form>
          <p v-if="manageLinkEmailStatus === 'sent'" class="text-xs text-[var(--color-sv-accent)] mt-2">
            {{ t('manage.emailSent') }}
          </p>
          <p v-if="manageLinkEmailStatus === 'error'" class="text-xs text-[var(--color-sv-accent)] mt-2">
            {{ t('common.error') }}
          </p>
        </section>

        <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
          <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-3">
            {{ t('manage.brandingSection') }}
          </h2>
          <div class="flex items-center gap-3 mb-4">
            <div class="w-20 h-10 shrink-0 rounded-lg border border-[var(--color-sv-gray-light)] flex items-center justify-center overflow-hidden p-1">
              <img
                :src="poll.branding?.logo_url || '/images/logo-simplevoter.png'"
                alt="Logo"
                class="max-w-full max-h-full object-contain"
              />
            </div>
            <span class="w-5 h-5 shrink-0 rounded-full ring-1 ring-black/10" :style="{ background: poll.branding?.primary_color || DEFAULT_PRIMARY }" />
            <span class="w-5 h-5 shrink-0 rounded-full ring-1 ring-black/10" :style="{ background: poll.branding?.accent_color || DEFAULT_ACCENT }" />
            <span class="text-xs text-[var(--color-sv-gray)] min-w-0">
              {{ hasCustomBranding ? t('manage.brandingCustom') : t('manage.brandingDefault') }}
            </span>
          </div>
          <button
            type="button"
            @click="openBrandingModal"
            class="w-full text-sm py-2 rounded-lg border border-[var(--color-sv-gray-light)] hover:border-[var(--color-sv-accent)] hover:text-[var(--color-sv-accent)] transition-colors"
          >
            {{ t('manage.brandingCustomize') }}
          </button>
        </section>

        <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
          <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-3">
            {{ t('manage.votingSection') }}
          </h2>
          <label class="flex items-center gap-2 text-sm mb-2 cursor-pointer">
            <input type="radio" name="voting_mode" :checked="!poll.allows_multiple_choice" @change="saveSettings({ allows_multiple_choice: false })" />
            {{ t('manage.singleChoice') }}
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" name="voting_mode" :checked="poll.allows_multiple_choice" @change="saveSettings({ allows_multiple_choice: true })" />
            {{ t('manage.multipleChoice') }}
          </label>
        </section>

        <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)]">
              {{ t('manage.questionsSection') }}
            </h2>
            <button
              type="button"
              role="switch"
              :aria-checked="poll.questions_enabled"
              @click="saveSettings({ questions_enabled: !poll.questions_enabled })"
              class="relative w-9 h-5 rounded-full transition-colors cursor-pointer"
              :style="{ background: poll.questions_enabled ? 'var(--color-sv-accent)' : 'var(--color-sv-gray-light)' }"
            >
              <span
                class="absolute top-0.5 w-4 h-4 rounded-full bg-white transition-all"
                :style="{ left: poll.questions_enabled ? '18px' : '2px' }"
              />
            </button>
          </div>

          <div :class="{ 'opacity-40 pointer-events-none': !poll.questions_enabled }">
            <label class="flex items-center gap-2 text-sm mb-2 cursor-pointer">
              <input type="radio" name="question_name_mode" :checked="poll.question_name_mode === 'hidden'" @change="saveSettings({ question_name_mode: 'hidden' })" />
              {{ t('manage.nameHidden') }}
            </label>
            <label class="flex items-center gap-2 text-sm mb-2 cursor-pointer">
              <input type="radio" name="question_name_mode" :checked="poll.question_name_mode === 'optional'" @change="saveSettings({ question_name_mode: 'optional' })" />
              {{ t('manage.nameOptional') }}
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="radio" name="question_name_mode" :checked="poll.question_name_mode === 'required'" @change="saveSettings({ question_name_mode: 'required' })" />
              {{ t('manage.nameRequired') }}
            </label>
          </div>
        </section>

        <!-- Umfragen-Box: nur sichtbar wenn Poll zu einem Event gehört -->
        <section v-if="poll.event" class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
          <div class="flex items-center justify-between mb-1">
            <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)]">
              {{ t('manage.pollsSection') }}
            </h2>
            <button
              v-if="poll.event.polls.length >= 2"
              type="button"
              @click="editingEventName = true; eventNameInput = poll.event.name || ''"
              class="text-xs text-[var(--color-sv-gray)] hover:text-[var(--color-sv-accent)] transition-colors"
            >
              {{ t('manage.editEventName') }}
            </button>
          </div>
          <p
            v-if="poll.event.name"
            class="text-sm font-medium text-[var(--color-sv-dark)] mb-3 mt-3"
          >{{ poll.event.name }}</p>
          <div v-else class="mb-3" />

          <ul class="space-y-2 mb-4">
            <li
              v-for="p in poll.event.polls"
              :key="p.id"
              draggable="true"
              @dragstart="onDragStart(p.id)"
              @dragover="onDragOver($event, p.id)"
              @drop="onDrop(p.id)"
              @dragend="onDragEnd"
              class="group rounded-lg border text-sm transition-colors"
              :class="[
                p.is_active
                  ? 'border-[var(--color-sv-accent)] bg-[var(--color-sv-accent-light)]'
                  : 'border-[var(--color-sv-gray-light)] hover:border-[var(--color-sv-accent)]',
                dragOverId === p.id && dragSourceId !== p.id ? 'ring-2 ring-[var(--color-sv-accent)]' : '',
                dragSourceId === p.id ? 'opacity-50' : '',
              ]"
            >
              <div
                class="flex items-start gap-2 px-3 py-2"
                :class="p.is_active ? 'text-[var(--color-sv-dark)]' : 'text-[var(--color-sv-gray)]'"
              >
                <span class="mt-0.5 cursor-grab text-[var(--color-sv-gray-light)] hover:text-[var(--color-sv-gray)] select-none shrink-0">⠿</span>
                <span
                  class="break-words flex-1 min-w-0 cursor-pointer"
                  @click="!p.is_active && activatePoll(p.id)"
                >{{ p.question }}</span>
                <span
                  v-if="p.is_active"
                  class="shrink-0 text-xs font-medium text-[var(--color-sv-accent)]"
                >
                  {{ t('manage.activePollBadge') }}
                </span>
              </div>
              <div
                v-if="poll.event.polls.length > 1"
                class="flex px-3 pb-2 opacity-0 group-hover:opacity-100 transition-opacity"
              >
                <button
                  type="button"
                  @click.stop="pollActionTarget = { pid: p.id, action: 'delete' }"
                  class="text-xs text-[var(--color-sv-gray)] hover:text-[var(--color-sv-accent)]"
                >
                  {{ t('manage.deletePoll') }}
                </button>
              </div>
            </li>
          </ul>

          <button
            type="button"
            @click="addPollModalOpen = true"
            class="w-full text-sm py-2 rounded-lg border border-[var(--color-sv-gray-light)] hover:border-[var(--color-sv-accent)] hover:text-[var(--color-sv-accent)] transition-colors"
          >
            + {{ t('manage.addPoll') }}
          </button>
        </section>

        <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
          <h2 class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-3">
            {{ t('manage.controlSection') }}
          </h2>

          <button
            type="button"
            @click="toggleActive"
            class="w-full text-sm py-2 rounded-lg border mb-2 transition-colors"
            :class="poll.is_active
              ? 'border-[var(--color-sv-gray-light)] hover:border-[var(--color-sv-accent)]'
              : 'border-[var(--color-sv-accent)] text-[var(--color-sv-accent)]'"
          >
            {{ poll.is_active ? t('manage.pause') : t('manage.resume') }}
          </button>
          <p v-if="!poll.is_active" class="text-xs text-[var(--color-sv-gray)] mb-4">
            {{ t('manage.pausedHint') }}
          </p>

          <button
            type="button"
            @click="downloadResults"
            class="w-full text-sm py-2 rounded-lg border border-[var(--color-sv-gray-light)] hover:border-[var(--color-sv-accent)] mb-2"
          >
            {{ t('manage.downloadResults') }}
          </button>

          <button
            type="button"
            @click="resetModalOpen = true"
            class="w-full text-sm py-2 rounded-lg border border-[var(--color-sv-gray-light)] text-[var(--color-sv-accent)] hover:bg-[var(--color-sv-accent-light)]"
            :class="poll.event?.polls.length === 1 ? 'mb-2' : ''"
          >
            {{ t('manage.reset') }}
          </button>

          <button
            v-if="poll.event?.polls.length === 1"
            type="button"
            @click="pollActionTarget = { pid: poll.id, action: 'delete' }"
            class="w-full text-sm py-2 rounded-lg border border-[var(--color-sv-gray-light)] text-[var(--color-sv-accent)] hover:bg-[var(--color-sv-accent-light)]"
          >
            {{ t('manage.deletePoll') }}
          </button>
        </section>
      </div>
    </main>

    <!-- Dedizierter Report für den PDF-Export (Ergebnis herunterladen), nur
         beim Drucken sichtbar. Eigenständig statt Bildschirm-Karte, damit der
         Export wie ein richtiges Dokument aussieht statt wie ein UI-Screenshot. -->
    <div id="pdf-report" class="hidden print:block">
      <img :src="poll.branding?.logo_url || '/images/logo-simplevoter.png'" alt="Logo" class="h-8 w-auto max-w-[12rem] object-contain object-left mb-10" />

      <p class="text-xs uppercase tracking-wide text-[#9ea7ae] mb-2">{{ t('manage.pdfExportLabel') }}</p>
      <h1 class="font-display font-semibold text-2xl mb-1 text-[#2b2c30] break-words">{{ poll.question }}</h1>
      <p v-if="poll.description" class="text-sm text-[#6b7178] mb-8 break-words">{{ poll.description }}</p>
      <div v-else class="mb-8" />

      <div class="space-y-6 min-w-0">
        <div v-for="option in poll.options" :key="option.id">
          <div class="flex items-baseline justify-between text-sm mb-1.5">
            <span class="font-medium text-[#2b2c30]">{{ option.label }}</span>
            <span class="font-mono-num text-[#6b7178]">{{ option.vote_count }} · {{ percent(option) }}%</span>
          </div>
          <div class="h-2.5 rounded-full bg-[#eef0f1] overflow-hidden">
            <div
              class="h-full rounded-full"
              :style="{ width: `${percent(option)}%`, background: poll.branding?.accent_color || '#bb3245' }"
            />
          </div>
        </div>
      </div>

      <div class="mt-12 pt-4 border-t border-[#eef0f1] flex items-baseline justify-between text-xs text-[#9ea7ae]">
        <span>{{ totalVotes }} {{ t('manage.pdfTotalVotes') }}</span>
        <span>{{ t('manage.pdfExportedOn') }} {{ exportDate }} · simplevoter.com</span>
      </div>

      <div v-if="poll.questions.length" class="mt-10 pt-8 border-t border-[#eef0f1]">
        <p class="text-xs uppercase tracking-wide text-[#9ea7ae] mb-4">
          {{ t('manage.pdfQuestionsCount', { n: poll.questions.length }) }}
        </p>
        <div class="space-y-4">
          <div v-for="q in poll.questions" :key="q.id" class="break-inside-avoid">
            <p class="text-sm text-[#2b2c30] break-words">{{ q.content }}</p>
            <p class="text-xs text-[#9ea7ae] mt-0.5">
              {{ q.author_name || t('manage.pdfAnonymous') }} · {{ new Date(q.created_at).toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' }) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Zurücksetzen-Bestätigung als eigenes Modal statt natives confirm() -->
    <Teleport to="body">
      <div
        v-if="resetModalOpen"
        class="fixed inset-0 bg-black/30 flex items-center justify-center z-50 px-6"
        @click.self="resetModalOpen = false"
      >
        <div class="bg-[var(--color-sv-surface)] w-full max-w-sm rounded-2xl p-6">
          <h2 class="font-display font-semibold text-lg mb-2">{{ t('manage.reset') }}</h2>
          <p class="text-sm text-[var(--color-sv-gray)] mb-6">{{ t('manage.resetConfirm') }}</p>
          <div class="flex gap-2">
            <button
              type="button"
              @click="resetModalOpen = false"
              class="flex-1 py-2 rounded-lg border border-[var(--color-sv-gray-light)] text-sm hover:border-[var(--color-sv-gray)]"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="button"
              @click="confirmReset"
              :disabled="resettingPoll"
              class="flex-1 py-2 rounded-lg bg-[var(--color-sv-accent)] text-white text-sm hover:opacity-90 disabled:opacity-50"
            >
              {{ t('manage.reset') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Footer />

    <!-- Modal: Umfrage hinzufügen -->
    <Teleport to="body">
      <div
        v-if="addPollModalOpen"
        class="fixed inset-0 bg-black/30 flex items-center justify-center z-50 px-6"
        @click.self="addPollModalOpen = false"
      >
        <div class="bg-[var(--color-sv-surface)] w-full max-w-sm rounded-2xl p-6">
          <h2 class="font-display font-semibold text-lg mb-4">{{ t('manage.addPoll') }}</h2>
          <form @submit.prevent="submitAddPoll" class="space-y-3">
            <textarea
              v-model="newPollQuestion"
              :placeholder="t('manage.newPollQuestionPlaceholder')"
              rows="2"
              maxlength="500"
              class="w-full text-sm px-3 py-2 rounded-lg border border-[var(--color-sv-gray-light)] focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)] resize-none"
            />
            <div class="flex gap-2">
              <button
                type="button"
                @click="addPollModalOpen = false"
                class="flex-1 py-2 rounded-lg border border-[var(--color-sv-gray-light)] text-sm hover:border-[var(--color-sv-gray)]"
              >
                {{ t('common.cancel') }}
              </button>
              <button
                type="submit"
                :disabled="!newPollQuestion.trim() || addingPoll"
                class="flex-1 py-2 rounded-lg bg-[var(--color-sv-dark)] text-white text-sm hover:bg-[var(--color-sv-accent)] disabled:opacity-40"
              >
                {{ t('manage.createPoll') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Modal: Poll aus Event entfernen / löschen -->
    <Teleport to="body">
      <div
        v-if="pollActionTarget"
        class="fixed inset-0 bg-black/30 flex items-center justify-center z-50 px-6"
        @click.self="pollActionTarget = null"
      >
        <div class="bg-[var(--color-sv-surface)] w-full max-w-sm rounded-2xl p-6">
          <h2 class="font-display font-semibold text-lg mb-2">
            {{ pollActionTarget?.action === 'delete' ? t('manage.deletePoll') : t('manage.detachPoll') }}
          </h2>
          <p class="text-sm text-[var(--color-sv-gray)] mb-6">
            {{ pollActionTarget?.action === 'delete' ? t('manage.deletePollConfirm') : t('manage.detachPollConfirm') }}
          </p>
          <div class="flex gap-2">
            <button
              type="button"
              @click="pollActionTarget = null"
              class="flex-1 py-2 rounded-lg border border-[var(--color-sv-gray-light)] text-sm hover:border-[var(--color-sv-gray)]"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="button"
              @click="confirmPollAction"
              :disabled="pollActionPending"
              class="flex-1 py-2 rounded-lg bg-[var(--color-sv-accent)] text-white text-sm hover:opacity-90 disabled:opacity-50"
            >
              {{ pollActionTarget?.action === 'delete' ? t('common.delete') : t('manage.detachPoll') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal: Eigenes Design (Logo + Farben) -->
    <Teleport to="body">
      <div
        v-if="brandingModalOpen"
        class="fixed inset-0 bg-black/30 flex items-end sm:items-center justify-center z-50 sm:px-6"
        @click.self="brandingModalOpen = false"
      >
        <div class="bg-[var(--color-sv-surface)] w-full sm:max-w-md sm:rounded-2xl rounded-t-2xl p-6 max-h-[90vh] overflow-y-auto">
          <h2 class="font-display font-semibold text-lg mb-1">{{ t('manage.brandingTitle') }}</h2>
          <p class="text-sm text-[var(--color-sv-gray)] mb-5">{{ t('manage.brandingHint') }}</p>

          <!-- Logo -->
          <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-2">{{ t('manage.brandingLogo') }}</p>
          <input
            ref="brandingLogoInput"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="hidden"
            @change="selectBrandingLogo($event.target.files[0])"
          />
          <button
            type="button"
            @click="brandingLogoInput.click()"
            @dragover.prevent
            @drop.prevent="selectBrandingLogo($event.dataTransfer.files[0])"
            class="w-full h-24 rounded-xl border-2 border-dashed border-[var(--color-sv-gray-light)] hover:border-[var(--color-sv-gray)] flex flex-col items-center justify-center gap-1 p-3 transition-colors"
          >
            <img v-if="brandingForm.logoPreview" :src="brandingForm.logoPreview" alt="Logo" class="max-h-full max-w-full object-contain" />
            <template v-else>
              <span class="text-sm">{{ t('manage.brandingLogoUpload') }}</span>
              <span class="text-xs text-[var(--color-sv-gray)]">{{ t('manage.brandingLogoFormats') }}</span>
            </template>
          </button>
          <div class="h-6 mt-1 mb-3">
            <button
              v-if="brandingForm.logoPreview"
              type="button"
              @click="removeBrandingLogo"
              class="text-xs text-[var(--color-sv-gray)] hover:text-[var(--color-sv-accent)]"
            >
              {{ t('manage.brandingLogoRemove') }}
            </button>
          </div>

          <!-- Farben -->
          <div class="grid grid-cols-2 gap-3 mb-5">
            <div v-for="field in ['primary', 'accent']" :key="field">
              <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)]">
                {{ field === 'primary' ? t('manage.brandingPrimary') : t('manage.brandingAccent') }}
              </p>
              <p class="text-xs text-[var(--color-sv-gray)] mb-2">
                {{ field === 'primary' ? t('manage.brandingPrimaryHint') : t('manage.brandingAccentHint') }}
              </p>
              <div class="flex items-center gap-2 rounded-lg border border-[var(--color-sv-gray-light)] p-1.5 focus-within:ring-2 focus-within:ring-[var(--color-sv-accent)]">
                <input
                  type="color"
                  :value="HEX_COLOR.test(brandingForm[field]) ? brandingForm[field] : '#000000'"
                  @input="brandingForm[field] = $event.target.value"
                  class="sv-color-swatch w-8 h-8 shrink-0 rounded-md cursor-pointer"
                />
                <input
                  v-model.trim="brandingForm[field]"
                  maxlength="7"
                  spellcheck="false"
                  class="w-full min-w-0 font-mono-num text-sm uppercase focus:outline-none"
                />
              </div>
            </div>
          </div>

          <!-- Vorschau -->
          <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] mb-2">{{ t('manage.brandingPreview') }}</p>
          <div :style="brandingPreviewStyle" class="rounded-xl bg-[var(--color-sv-bg)] p-4 mb-5">
            <img v-if="brandingForm.logoPreview" :src="brandingForm.logoPreview" alt="Logo" class="max-h-8 max-w-32 object-contain mb-3" />
            <img v-else src="/images/logo-simplevoter.png" alt="SimpleVoter" class="w-28 h-auto mb-3" />
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-[var(--color-sv-accent)] bg-[var(--color-sv-accent-light)] text-sm mb-2">
              <span class="w-3 h-3 rounded-full border-[3px] border-[var(--color-sv-accent)] bg-white shrink-0" />
              <span class="truncate">{{ t('manage.brandingPreviewOption') }}</span>
              <span class="ml-auto h-1.5 w-16 rounded-full bg-[var(--color-sv-gray-light)] overflow-hidden shrink-0">
                <span class="block h-full w-2/3 bg-[var(--color-sv-accent)]" />
              </span>
            </div>
            <span class="inline-block px-4 py-1.5 rounded-lg bg-[var(--sv-primary)] text-[var(--sv-on-primary)] text-sm font-medium">
              {{ t('public.vote') }}
            </span>
          </div>

          <p v-if="brandingError" class="text-sm text-[var(--color-sv-accent)] mb-3">{{ brandingError }}</p>

          <div class="flex gap-2">
            <button
              type="button"
              @click="brandingModalOpen = false"
              class="flex-1 py-2 rounded-lg border border-[var(--color-sv-gray-light)] text-sm hover:border-[var(--color-sv-gray)]"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="button"
              @click="saveBranding"
              :disabled="savingBranding"
              class="flex-1 py-2 rounded-lg bg-[var(--color-sv-dark)] text-white text-sm hover:bg-[var(--color-sv-accent)] disabled:opacity-40"
            >
              {{ t('manage.save') }}
            </button>
          </div>
          <button
            type="button"
            @click="resetBrandingForm"
            class="w-full mt-3 text-xs text-[var(--color-sv-gray)] hover:text-[var(--color-sv-accent)]"
          >
            {{ t('manage.brandingReset') }}
          </button>
        </div>
      </div>
    </Teleport>

    <!-- Modal: Event-Name bearbeiten -->
    <Teleport to="body">
      <div
        v-if="editingEventName"
        class="fixed inset-0 bg-black/30 flex items-center justify-center z-50 px-6"
        @click.self="editingEventName = false"
      >
        <div class="bg-[var(--color-sv-surface)] w-full max-w-sm rounded-2xl p-6">
          <h2 class="font-display font-semibold text-lg mb-4">{{ t('manage.editEventName') }}</h2>
          <input
            v-model="eventNameInput"
            :placeholder="t('manage.eventNamePlaceholder')"
            maxlength="200"
            class="w-full text-sm px-3 py-2 rounded-lg border border-[var(--color-sv-gray-light)] focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)] mb-4"
          />
          <div class="flex gap-2">
            <button
              type="button"
              @click="editingEventName = false"
              class="flex-1 py-2 rounded-lg border border-[var(--color-sv-gray-light)] text-sm hover:border-[var(--color-sv-gray)]"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="button"
              @click="saveEventName"
              :disabled="savingEventName"
              class="flex-1 py-2 rounded-lg bg-[var(--color-sv-dark)] text-white text-sm hover:bg-[var(--color-sv-accent)] disabled:opacity-40"
            >
              {{ t('manage.save') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import { useI18n } from 'vue-i18n'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

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

onMounted(async () => {
  await nextTick()
  autoGrowQuestion()
  autoGrowDescription()

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
    <Head :title="`${poll.question} – Verwalten – SimpleVoter`" />

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
              <div class="flex items-center justify-between text-sm mb-1 min-w-0">
                <input
                  :value="option.label"
                  @change="updateOptionLabel(option, $event.target.value)"
                  @focus="isEditingField = true"
                  @blur="isEditingField = false"
                  class="font-medium bg-transparent min-w-0 max-w-full box-border focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)] rounded px-1"
                />
                <span class="font-mono-num text-[var(--color-sv-gray)]">{{ option.vote_count }} {{ t('manage.votes') }}</span>
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
              class="shrink-0 text-sm px-4 py-2 rounded-lg bg-[var(--color-sv-dark)] text-white hover:bg-[var(--color-sv-accent)] disabled:opacity-40"
            >
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
          >
            {{ t('manage.reset') }}
          </button>
        </section>
      </div>
    </main>

    <!-- Dedizierter Report für den PDF-Export (Ergebnis herunterladen), nur
         beim Drucken sichtbar. Eigenständig statt Bildschirm-Karte, damit der
         Export wie ein richtiges Dokument aussieht statt wie ein UI-Screenshot. -->
    <div id="pdf-report" class="hidden print:block">
      <img src="/images/logo-simplevoter.png" alt="SimpleVoter" class="h-8 w-auto mb-10" />

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
              :style="{ width: `${percent(option)}%`, background: '#bb3245' }"
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useVoterToken, useAuthorToken } from '@/composables/useDeviceToken'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

const props = defineProps({
  poll: { type: Object, required: true },
  initialState: { type: Object, required: true },
  publicToken: { type: String, required: true },
})

const { t } = useI18n()

const voterToken = useVoterToken(props.publicToken)
const authorToken = useAuthorToken(props.publicToken)

const state = ref(props.initialState)
const selected = ref([]) // array of poll_option_id (auch bei Single-Choice, dann max. 1 Eintrag)
const submittingVote = ref(false)
const flashOptionIds = ref(new Set())

const questionsPanelOpen = ref(false)
const questionContent = ref('')
const questionAuthorName = ref('')
const submittingQuestion = ref(false)
const questionError = ref('')
const website = ref('') // Honeypot
const questionsList = ref([])

const lastSeenKey = `sv_last_seen_question_${props.publicToken}`
const hasNewQuestions = computed(() => {
  const lastSeen = Number(localStorage.getItem(lastSeenKey) ?? 0)
  return (state.value.latest_question_id ?? 0) > lastSeen
})

const maxVotes = computed(() =>
  Math.max(1, ...state.value.options.map((o) => o.vote_count || 0))
)

const showResults = computed(() =>
  state.value.options.every((o) => o.vote_count !== null)
)

let pollTimer = null

async function refreshState() {
  const { data } = await axios.get(`/w/${props.publicToken}/state`, {
    params: { voter_token: voterToken },
  })

  // Vote-Zahlen, die sich seit dem letzten Tick geändert haben, kurz aufblitzen
  // lassen (Signature-Element: "Live"-Charakter der Ergebnisse).
  data.options.forEach((next) => {
    const prev = state.value.options.find((o) => o.id === next.id)
    if (prev && prev.vote_count !== next.vote_count) {
      flashOptionIds.value.add(next.id)
      setTimeout(() => flashOptionIds.value.delete(next.id), 600)
    }
  })

  state.value = data
}

function toggleSelect(optionId) {
  if (state.value.poll.allows_multiple_choice) {
    const idx = selected.value.indexOf(optionId)
    if (idx === -1) selected.value.push(optionId)
    else selected.value.splice(idx, 1)
  } else {
    selected.value = [optionId]
  }
}

async function submitVote() {
  if (!selected.value.length || submittingVote.value) return
  submittingVote.value = true

  try {
    for (const optionId of selected.value) {
      await axios.post(`/w/${props.publicToken}/vote`, {
        poll_option_id: optionId,
        voter_token: voterToken,
      })
    }
    await refreshState()
  } finally {
    submittingVote.value = false
  }
}

async function loadQuestions() {
  const { data } = await axios.get(`/w/${props.publicToken}/questions`)
  questionsList.value = data
}

async function openQuestionsPanel() {
  questionsPanelOpen.value = true
  localStorage.setItem(lastSeenKey, String(state.value.latest_question_id ?? 0))
  await loadQuestions()
}

async function submitQuestion() {
  if (!questionContent.value.trim() || submittingQuestion.value) return
  submittingQuestion.value = true
  questionError.value = ''

  try {
    await axios.post(`/w/${props.publicToken}/questions`, {
      content: questionContent.value.trim(),
      author_name: questionAuthorName.value.trim() || undefined,
      author_token: authorToken,
      website: website.value,
    })
    questionContent.value = ''
    questionAuthorName.value = ''
    await refreshState()
    await loadQuestions()
    localStorage.setItem(lastSeenKey, String(state.value.latest_question_id ?? 0))
  } catch (e) {
    // Fehler sichtbar machen statt still zu verschlucken – Status/Meldung
    // helfen beim Debuggen (z.B. 422 Validierung, 429 Rate-Limit, 419 CSRF).
    console.error('Frage konnte nicht gesendet werden:', e.response?.status, e.response?.data)
    questionError.value = e.response?.data?.message || t('common.error')
  } finally {
    submittingQuestion.value = false
  }
}

onMounted(() => {
  pollTimer = setInterval(refreshState, 6000)
})
onUnmounted(() => clearInterval(pollTimer))
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <Head :title="`${poll.question} – SimpleVoter`" />

    <header class="flex items-center justify-between px-6 py-3 max-w-2xl w-full mx-auto">
      <img src="/images/logo-simplevoter.png" alt="SimpleVoter" class="w-44 h-auto" />
      <div class="flex items-center gap-1">
        <LanguageSwitcher />

        <button
          v-if="poll.questions_enabled"
          type="button"
          @click="openQuestionsPanel"
          class="relative w-9 h-9 flex items-center justify-center cursor-pointer hover:opacity-70 transition-opacity"
          :aria-label="t('public.askQuestion')"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 110" width="38" height="27">
            <path d="M 30 10 L 90 10 C 104 10 112 18 112 32 L 112 60 C 112 74 104 82 90 82 L 72 82 L 35 102 L 45 82 L 30 82 C 16 82 8 74 8 60 L 8 32 C 8 18 16 10 30 10 Z" fill="none" stroke="var(--color-sv-dark)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span
            v-if="hasNewQuestions"
            class="absolute top-0 right-0 w-2.5 h-2.5 rounded-full bg-[var(--color-sv-accent)] ring-2 ring-[var(--color-sv-bg)]"
          />
        </button>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-6 pb-16 flex-1 w-full">
      <h1 class="font-display font-semibold text-3xl mb-2 leading-tight break-words">{{ poll.question }}</h1>
      <p v-if="poll.description" class="text-[var(--color-sv-gray)] mb-8 break-words">{{ poll.description }}</p>

      <p v-if="!state.poll.is_active && state.options.length" class="text-sm text-[var(--color-sv-accent)] mb-6">
        {{ t('public.pollClosed') }}
      </p>

      <template v-if="state.options.length">
        <div class="space-y-3 mb-6">
          <label
            v-for="option in state.options"
            :key="option.id"
            class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
            :class="selected.includes(option.id)
              ? 'border-[var(--color-sv-accent)] bg-[var(--color-sv-accent-light)]'
              : 'border-[var(--color-sv-gray-light)] bg-[var(--color-sv-surface)]'"
          >
            <div class="flex items-center gap-3 flex-1">
              <input
                :type="state.poll.allows_multiple_choice ? 'checkbox' : 'radio'"
                :name="state.poll.allows_multiple_choice ? undefined : 'poll_option'"
                :checked="selected.includes(option.id)"
                @change="toggleSelect(option.id)"
                :disabled="!state.poll.is_active"
                class="accent-[var(--color-sv-accent)]"
              />
              <span class="text-sm">{{ option.label }}</span>
            </div>

            <div v-if="showResults" class="flex items-center gap-3 w-1/3">
              <div class="h-1.5 flex-1 rounded-full bg-[var(--color-sv-gray-light)] overflow-hidden">
                <div
                  class="h-full bg-[var(--color-sv-accent)] transition-all duration-500 ease-out rounded-full"
                  :style="{ width: `${(option.vote_count / maxVotes) * 100}%` }"
                />
              </div>
              <span
                class="font-mono-num text-xs text-[var(--color-sv-gray)] w-6 text-right"
                :class="{ 'sv-flash': flashOptionIds.has(option.id) }"
              >
                {{ option.vote_count }}
              </span>
            </div>
          </label>
        </div>

        <p v-if="!showResults" class="text-sm text-[var(--color-sv-gray)] mb-6">
          {{ state.poll.result_visibility === 'after_vote'
            ? t('public.resultsHiddenUntilVote')
            : t('public.resultsHiddenUntilClosed') }}
        </p>

        <button
          type="button"
          @click="submitVote"
          :disabled="!selected.length || !state.poll.is_active || submittingVote"
          class="w-full sm:w-auto px-6 py-3 rounded-xl bg-[var(--color-sv-dark)] text-white font-medium hover:bg-[var(--color-sv-accent)] transition-colors disabled:opacity-40"
        >
          {{ state.has_voted ? t('public.changeVote') : t('public.vote') }}
        </button>
        <p v-if="state.has_voted" class="text-sm text-[var(--color-sv-accent)] mt-3">{{ t('public.voted') }}</p>
      </template>

      <p v-else class="text-sm text-[var(--color-sv-gray)]">
        {{ t('public.noOptionsHint') }}
      </p>
    </main>

    <Footer :powered-by="true" />

    <!-- Fragen-Panel (Abschnitt 7) -->
    <Teleport to="body">
      <div
        v-if="questionsPanelOpen"
        class="fixed inset-0 bg-black/30 flex items-end sm:items-center justify-center z-50"
        @click.self="questionsPanelOpen = false"
      >
        <div class="bg-[var(--color-sv-surface)] w-full sm:max-w-md sm:rounded-2xl rounded-t-2xl p-6 max-h-[80vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-display font-semibold text-lg">{{ t('public.questionsTitle') }}</h2>
            <button type="button" @click="questionsPanelOpen = false" class="text-[var(--color-sv-gray)]">✕</button>
          </div>

          <form @submit.prevent="submitQuestion" class="space-y-2 mb-6">
            <textarea
              v-model="questionContent"
              :placeholder="t('public.yourQuestion')"
              maxlength="1000"
              rows="2"
              class="w-full text-sm px-3 py-2 rounded-lg border border-[var(--color-sv-gray-light)] focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)] resize-none"
            />
            <input
              v-if="poll.question_name_mode !== 'hidden'"
              v-model="questionAuthorName"
              :placeholder="poll.question_name_mode === 'required' ? t('public.yourNameRequired') : t('public.yourName')"
              :required="poll.question_name_mode === 'required'"
              maxlength="100"
              class="w-full text-sm px-3 py-2 rounded-lg border border-[var(--color-sv-gray-light)] focus:outline-none focus:ring-2 focus:ring-[var(--color-sv-accent)]"
            />
            <input v-model="website" tabindex="-1" autocomplete="off" class="absolute -left-[9999px] w-px h-px opacity-0" aria-hidden="true" />
            <p v-if="questionError" class="text-sm text-[var(--color-sv-accent)]">{{ questionError }}</p>
            <button
              type="submit"
              :disabled="!questionContent.trim() || submittingQuestion"
              class="w-full mt-2 py-2 rounded-lg bg-[var(--color-sv-dark)] text-white text-sm hover:bg-[var(--color-sv-accent)] disabled:opacity-40"
            >
              {{ t('public.send') }}
            </button>
            <button
              type="button"
              @click="questionsPanelOpen = false"
              class="w-full py-2 rounded-lg border border-[var(--color-sv-gray-light)] text-sm text-[var(--color-sv-gray)] hover:border-[var(--color-sv-accent)] hover:text-[var(--color-sv-accent)]"
            >
              {{ t('common.close') }}
            </button>
          </form>

          <p v-if="!questionsList.length" class="text-sm text-[var(--color-sv-gray)]">
            {{ t('public.noQuestionsYet') }}
          </p>
          <ul v-else class="space-y-3">
            <li v-for="q in questionsList" :key="q.id" class="border-b border-[var(--color-sv-gray-light)] pb-3 last:border-0">
              <p class="text-sm break-words">{{ q.content }}</p>
              <p v-if="q.author_name" class="text-xs text-[var(--color-sv-gray)] mt-1">— {{ q.author_name }}</p>
            </li>
          </ul>
        </div>
      </div>
    </Teleport>
  </div>
</template>

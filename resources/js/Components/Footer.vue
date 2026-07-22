<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const year = new Date().getFullYear()

const props = defineProps({
  poweredBy: { type: Boolean, default: false },
})
</script>

<template>
  <footer class="border-t border-[var(--color-sv-gray-light)] mt-16">
    <div class="max-w-2xl mx-auto px-6 pt-6 pb-10 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-[var(--color-sv-gray)]">
      <template v-if="poweredBy">
        <a
          href="https://simplevoter.com"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-2 group"
        >
          <span class="text-xs">Powered by</span>
          <img src="/images/logo-simplevoter.png" alt="SimpleVoter" class="w-24 h-auto opacity-50 group-hover:opacity-90 transition-opacity" />
        </a>
      </template>
      <template v-else>
        <span>© {{ year }} <a href="/">SimpleVoter</a> — {{ t('footer.rights') }}</span>
      </template>
      <nav class="flex items-center gap-4 text-xs">
        <a href="/" class="hover:text-[var(--color-sv-accent)]">{{ t('footer.home') }}</a>
        <a href="/datenschutz" class="hover:text-[var(--color-sv-accent)]">{{ t('footer.privacy') }}</a>
        <a href="/impressum" class="hover:text-[var(--color-sv-accent)]">{{ t('footer.imprint') }}</a>
      </nav>
    </div>
  </footer>
</template>

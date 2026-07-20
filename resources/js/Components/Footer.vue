<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const year = new Date().getFullYear()
</script>

<template>
  <footer class="border-t border-[var(--color-sv-gray-light)] mt-16">
    <div class="max-w-2xl mx-auto px-6 pt-6 pb-10 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-[var(--color-sv-gray)]">
      <span>© {{ year }} SimpleVoter — {{ t('footer.rights') }}</span>
      <nav class="flex items-center gap-4">
        <a href="/datenschutz" class="hover:text-[var(--color-sv-accent)]">{{ t('footer.privacy') }}</a>
        <a href="/impressum" class="hover:text-[var(--color-sv-accent)]">{{ t('footer.imprint') }}</a>
      </nav>
    </div>
  </footer>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { Head } from '@inertiajs/vue3'
import Footer from '@/Components/Footer.vue'
import { onMounted, ref } from 'vue'

const { t } = useI18n()

// JS-Schutz: E-Mail und Telefon werden erst client-seitig zusammengesetzt,
// damit einfache E-Mail-Harvester und Telefon-Scraper nichts im HTML finden.
// Die Bestandteile sind im Quellcode nicht als fertige Strings vorhanden.
const email = ref('')
const phone = ref('')

onMounted(() => {
  const u = 'hello'
  const d = 'simplevoter' + '.' + 'com'
  email.value = `${u}@${d}`

  const country = '+49'
  const num = ['170', '481', '4147'].join(' ')
  phone.value = `${country} ${num}`
})
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <Head title="Impressum – SimpleVoter" />
    <header class="px-6 py-5 max-w-2xl w-full mx-auto">
      <a href="/" class="font-display font-semibold text-lg tracking-tight"><img src="/images/logo-simplevoter.png" alt="SimpleVoter" class="w-44 h-auto" /></a>
    </header>

    <main class="flex-1 max-w-2xl mx-auto px-6 py-10 w-full">
      <h1 class="font-display font-semibold text-2xl mb-6">{{ t('legal.imprintTitle') }}</h1>

      <div class="prose prose-sm text-[var(--color-sv-dark)] space-y-6">

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Anbieter</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            TM Systems Till Merlé<br />
            Birkenstr. 19<br />
            61440 Oberursel<br />
            Germany
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Kontakt</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            E-Mail:
            <a v-if="email" :href="`mailto:${email}`" class="hover:text-[var(--color-sv-accent)]">{{ email }}</a>
            <span v-else>–</span>
            <br />
            Telefon:
            <a v-if="phone" :href="`tel:${phone.replace(/\s/g,'')}`" class="hover:text-[var(--color-sv-accent)]">{{ phone }}</a>
            <span v-else>–</span>
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Vertretungsberechtigt</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            Till Merlé
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            TM Systems Till Merlé, Birkenstr. 19, 61440 Oberursel, Germany
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Streitschlichtung</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
            <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener" class="hover:text-[var(--color-sv-accent)]">ec.europa.eu/consumers/odr/</a>.
            Wir sind nicht verpflichtet und nicht bereit, an
            Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.
          </p>
        </section>
      </div>

      <a href="/" class="inline-block mt-10 text-sm text-[var(--color-sv-accent)] hover:opacity-80">
        ← {{ t('legal.back') }}
      </a>
    </main>

    <Footer />
  </div>
</template>

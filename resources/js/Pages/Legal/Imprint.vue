<script setup>
import { useI18n } from 'vue-i18n'
import { Head } from '@inertiajs/vue3'
import Footer from '@/Components/Footer.vue'

const { t } = useI18n()
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <Head title="Impressum – SimpleVoter" />
    <header class="px-6 py-5 max-w-2xl w-full mx-auto">
      <a href="/" class="font-display font-semibold text-lg tracking-tight">SimpleVoter</a>
    </header>

    <main class="flex-1 max-w-2xl mx-auto px-6 py-10 w-full">
      <h1 class="font-display font-semibold text-2xl mb-6">{{ t('legal.imprintTitle') }}</h1>

      <div class="prose prose-sm text-[var(--color-sv-dark)] space-y-6">
        <!--
          TODO: Angaben gemäß § 5 TMG ergänzen. Diese Struktur ist eine
          Orientierung, keine Rechtsberatung – bitte vor Live-Gang prüfen
          (lassen).
        -->
        <section>
          <h2 class="font-display font-semibold text-base mb-1">Anbieter</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            [Vollständiger Name / Firmenname]<br />
            [Straße Hausnummer]<br />
            [PLZ Ort]<br />
            [Land]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Kontakt</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            E-Mail: [kontakt@simplevoter.com]<br />
            Telefon: [optional]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Vertretungsberechtigt</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            [Name der vertretungsberechtigten Person, falls juristische Person]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Registereintrag</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            [Falls zutreffend: Registergericht, Registernummer]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Umsatzsteuer-ID</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            [Falls zutreffend: USt-IdNr. gem. § 27a UStG]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            [Name, Anschrift wie oben]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">Streitschlichtung</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
            https://ec.europa.eu/consumers/odr/. Wir sind nicht verpflichtet und nicht bereit, an
            Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.
            [Anpassen, falls Teilnahmepflicht besteht.]
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

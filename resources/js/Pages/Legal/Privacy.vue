<script setup>
import { useI18n } from 'vue-i18n'
import { Head } from '@inertiajs/vue3'
import Footer from '@/Components/Footer.vue'

const { t } = useI18n()
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <Head title="Datenschutz – SimpleVoter" />
    <header class="px-6 py-5 max-w-2xl w-full mx-auto">
      <a href="/" class="font-display font-semibold text-lg tracking-tight">SimpleVoter</a>
    </header>

    <main class="flex-1 max-w-2xl mx-auto px-6 py-10 w-full">
      <h1 class="font-display font-semibold text-2xl mb-6">{{ t('legal.privacyTitle') }}</h1>

      <div class="prose prose-sm text-[var(--color-sv-dark)] space-y-6">
        <!--
          TODO: Struktur orientiert an Art. 13 DSGVO, ersetzt keine
          Rechtsberatung. Bitte vor Live-Gang von fachkundiger Stelle
          prüfen (lassen) und auf den tatsächlichen Betrieb (Hosting,
          ggf. Tracking/Analytics) anpassen.
        -->
        <section>
          <h2 class="font-display font-semibold text-base mb-1">1. Verantwortlicher</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            [Vollständiger Name / Firmenname]<br />
            [Straße Hausnummer, PLZ Ort]<br />
            E-Mail: [kontakt@simplevoter.com]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">2. Server-Logfiles</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            Beim Erstellen einer Umfrage speichern wir die IP-Adresse der erstellenden Person
            zur Missbrauchsprävention (creator_ip). Der Hosting-Provider erzeugt zusätzlich
            technische Server-Logfiles (aufgerufene Seite, Zeitpunkt, IP-Adresse), die
            automatisiert entstehen. [Hosting-Provider und dessen Auftragsverarbeitung hier
            benennen.]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">3. Lokale Speicherung im Browser (LocalStorage)</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            SimpleVoter verzichtet bewusst auf ein Login-System. Stattdessen werden zufällig
            erzeugte Tokens (Verwaltungs-Token, Teilnahme-Token) im LocalStorage deines Browsers
            gespeichert – nicht auf unseren Servern personenbezogen zugeordnet. Diese Tokens
            verlassen dein Gerät nur, wenn du die entsprechende Umfrage aufrufst oder abstimmst.
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">4. Eingebundene Schriftarten (Google Fonts)</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            Diese Seite lädt Schriftarten (Space Grotesk, Inter, IBM Plex Mono) zur Laufzeit von
            Google-Servern nach. Dabei kann die IP-Adresse deines Geräts an Google übertragen
            werden. [Falls das vermieden werden soll: Schriftarten stattdessen lokal einbinden
            (self-hosted), dann diesen Abschnitt entfernen.]
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">5. Speicherdauer</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            Umfragen inkl. Antwortoptionen, Stimmen und Fragen werden automatisch gelöscht,
            wenn 90 Tage lang keine neue Aktivität (Stimme oder Frage) stattgefunden hat.
          </p>
        </section>

        <section>
          <h2 class="font-display font-semibold text-base mb-1">6. Deine Rechte</h2>
          <p class="text-sm text-[var(--color-sv-gray)]">
            Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der
            Verarbeitung, Datenübertragbarkeit sowie Widerspruch gegen die Verarbeitung
            deiner personenbezogenen Daten. Wende dich dafür an die oben genannte Kontaktadresse.
            Außerdem besteht ein Beschwerderecht bei einer Datenschutzaufsichtsbehörde.
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

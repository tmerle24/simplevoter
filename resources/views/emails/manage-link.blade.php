@component('mail::message')
# Dein Verwaltungs-Link

Du hast bei SimpleVoter eine Umfrage erstellt:

**{{ $question }}**

Mit dem folgenden Link kannst du sie jederzeit verwalten — Einstellungen ändern, Ergebnisse einsehen, anhalten oder zurücksetzen. Bewahre ihn gut auf, er ist der einzige Zugang zur Verwaltung.

@component('mail::button', ['url' => $manageUrl, 'color' => 'error'])
Umfrage verwalten
@endcomponent

Falls du diese E-Mail nicht erwartet hast, kannst du sie einfach ignorieren.

Danke,<br>
SimpleVoter
@endcomponent

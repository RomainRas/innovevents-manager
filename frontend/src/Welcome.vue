<script setup>
// Page d'accueil publique : présentation de l'agence et de ses valeurs.
const values = [
  ['Sur mesure', 'Chaque séminaire, conférence ou soirée est conçu pour vos équipes, vos objectifs et votre image.'],
  ['Fiabilité', 'Un interlocuteur unique, des devis clairs et un suivi rigoureux de chaque prestataire.'],
  ['Exigence', 'Cinq ans d’expérience dans l’événementiel professionnel haut de gamme.'],
]
</script>
<template>
  <main class="home">
    <section class="hero" aria-labelledby="home-title">
      <div class="hero-text">
        <p class="eyebrow">Agence événementielle</p>
        <h1 id="home-title">Des événements professionnels cousus main</h1>
        <p>
          Fondée par Chloé et José, Innov’Events conçoit des séminaires, conférences et soirées d’entreprise qui
          marquent les esprits. Notre équipe de huit personnes accompagne chaque projet de l’idée jusqu’au jour J.
        </p>
        <RouterLink class="button" to="/demande-devis">Demander un devis</RouterLink>
      </div>
      <img
        src="/images/accueil-conference.jpg"
        alt="Conférence : scène éclairée par deux projecteurs devant un public"
        width="1200"
        height="750"
      />
    </section>
    <section class="values" aria-labelledby="values-title">
      <h2 id="values-title">Notre approche</h2>
      <ul>
        <li v-for="[title, text] in values" :key="title">
          <h3>{{ title }}</h3>
          <p>{{ text }}</p>
        </li>
      </ul>
    </section>
    <section class="gallery" aria-labelledby="gallery-title">
      <h2 id="gallery-title">Nos univers</h2>
      <div class="gallery-grid">
        <figure>
          <img
            src="/images/accueil-seminaire.jpg"
            alt="Séminaire : participants réunis autour de tables rondes"
            width="1200"
            height="750"
            loading="lazy"
          />
          <figcaption>Séminaires et réunions de direction</figcaption>
        </figure>
        <figure>
          <img
            src="/images/accueil-soiree.jpg"
            alt="Soirée d’entreprise : salle décorée de guirlandes lumineuses"
            width="1200"
            height="750"
            loading="lazy"
          />
          <figcaption>Soirées et célébrations</figcaption>
        </figure>
      </div>
      <p><RouterLink to="/evenements-publics">Découvrir nos événements réalisés</RouterLink></p>
    </section>
    <section class="cta" aria-labelledby="cta-title">
      <h2 id="cta-title">Un projet en préparation ?</h2>
      <p>Décrivez votre besoin en quelques minutes : Chloé vous recontacte rapidement.</p>
      <RouterLink class="button" to="/demande-devis">Demander un devis</RouterLink>
    </section>
  </main>
</template>

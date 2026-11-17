<script setup>
// Page d'accueil publique : présentation de l'agence, valeurs, univers d'événements et appel au devis.
const values = [
  ['Sur mesure', 'Des formats et des expériences pensés pour vos objectifs, votre culture et vos équipes.'],
  ['Fiabilité', 'Une organisation maîtrisée, du brief au jour J, pour un événement fluide et sincère.'],
  [
    'Exigence',
    'Un sens du détail, des lieux inspirants et des partenaires de confiance pour un résultat à la hauteur de vos ambitions.',
  ],
]
// Programme type affiché sur le ticket décoratif du bandeau.
const programme = [
  ['09:00', 'Accueil'],
  ['09:30', 'Conférence'],
  ['12:30', 'Cocktail'],
  ['14:00', 'Ateliers'],
  ['19:30', 'Soirée'],
]
</script>
<template>
  <main class="home">
    <!-- Bandeau : présentation de l'agence sur fond rouge, photo de soirée et ticket de programme -->
    <section class="hero" aria-labelledby="home-title">
      <div class="hero-text">
        <p class="label">Agence événementielle</p>
        <h1 id="home-title">
          <span class="line-white">Des événements</span> <span>professionnels</span>
          <span class="highlight">cousus main</span>
        </h1>
        <p class="intro">
          Chloé et José, et toute leur équipe, créent pour vous des séminaires, des conférences et des soirées
          d’entreprise qui rassemblent, engagent et marquent les esprits.
        </p>
        <RouterLink class="paper-button" to="/demande-devis"
          >Demander un devis <span aria-hidden="true">→</span></RouterLink
        >
      </div>
      <div class="hero-visual">
        <img
          src="/images/accueil-soiree-danse.webp"
          alt="Soirée d’entreprise : une invitée éclate de rire sur la piste de danse, entourée de collègues"
          width="1600"
          height="900"
          fetchpriority="high"
        />
        <!-- Ticket décoratif : programme type d'une journée -->
        <div class="ticket" aria-hidden="true">
          <p class="ticket-title">Programme événement</p>
          <ul>
            <li v-for="[hour, step] in programme" :key="hour">
              <span>{{ hour }}</span
              ><span>{{ step }}</span>
            </li>
          </ul>
          <p class="ticket-number">N° 048392</p>
        </div>
      </div>
    </section>
    <!-- Valeurs de l'agence -->
    <section class="values" aria-labelledby="values-title">
      <h2 id="values-title"><span>Notre</span> <span>approche</span></h2>
      <ul>
        <li v-for="[title, text] in values" :key="title">
          <h3>
            <span class="star" aria-hidden="true">✱</span> <span class="mark">{{ title }}</span>
          </h3>
          <p>{{ text }}</p>
        </li>
      </ul>
    </section>
    <!-- Univers d'événements : deux photos légendées -->
    <section class="universes" aria-labelledby="universes-title">
      <h2 id="universes-title"><span>Nos</span> <span>univers</span></h2>
      <div class="photos">
        <figure class="tilt-left">
          <img
            src="/images/accueil-seminaire.webp"
            alt="Séminaire : une intervenante au micro devant des participants assis autour de tables"
            width="1000"
            height="750"
            loading="lazy"
          />
          <figcaption>Séminaires et réunions de direction</figcaption>
        </figure>
        <figure class="tilt-right">
          <img
            src="/images/accueil-diner.webp"
            alt="Dîner de gala : invités souriants autour d’une table décorée de fleurs rouges et bleues"
            width="1000"
            height="750"
            loading="lazy"
          />
          <figcaption>Soirées et célébrations</figcaption>
        </figure>
      </div>
      <p class="more">
        <RouterLink to="/evenements-publics"
          >Découvrir nos événements réalisés <span aria-hidden="true">→</span></RouterLink
        >
      </p>
    </section>
    <!-- Appel à demander un devis -->
    <section class="cta" aria-labelledby="cta-title">
      <h2 id="cta-title">Un projet en préparation ?</h2>
      <div class="cta-text">
        <p>
          <strong>Parlons de votre événement !</strong> Séminaire, conférence, soirée d’entreprise… Dites-nous ce que
          vous imaginez, on s’occupe du reste.
        </p>
        <RouterLink class="paper-button" to="/demande-devis"
          >Demander un devis <span aria-hidden="true">→</span></RouterLink
        >
      </div>
    </section>
  </main>
</template>
<style scoped>
/* Palette de l'affiche : papier crème, rouge, bleu et jaune, encre noire. */
.home {
  --paper: var(--papier);
  --red: var(--rouge);
  --blue: var(--bleu);
  --yellow: var(--jaune);
  --ink: var(--encre);
  margin-bottom: -72px;
  background: var(--paper);
  color: var(--ink);
  overflow: hidden;
}
h1,
h2,
.label,
.paper-button,
figcaption,
.ticket-title,
.mark {
  font-family: var(--police-titre);
  font-weight: 400;
  text-transform: uppercase;
  letter-spacing: 0.01em;
}
/* Bouton « papier » : fond crème, ombre portée noire, légèrement incliné. */
.paper-button {
  display: inline-flex;
  gap: 12px;
  align-items: center;
  min-height: 48px;
  padding: 12px 28px;
  background: var(--paper);
  color: var(--ink);
  font-size: 1.35rem;
  text-decoration: none;
  box-shadow: 5px 5px 0 var(--ink);
  transform: rotate(-1deg);
  transition: transform 0.15s;
}
.paper-button:hover {
  transform: rotate(0) translate(-2px, -2px);
}

/* Bandeau rouge, bord inférieur déchiré. */
.hero {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
  align-items: center;
  gap: 32px;
  padding: 56px max(16px, 4vw) 88px;
  background: var(--red);
  clip-path: polygon(
    0 0,
    100% 0,
    100% 96%,
    92% 98%,
    84% 95%,
    73% 99%,
    61% 96%,
    49% 100%,
    37% 96%,
    25% 99%,
    14% 95%,
    6% 98%,
    0 96%
  );
}
.label {
  display: inline-block;
  margin: 0 0 20px;
  padding: 6px 16px;
  background: var(--paper);
  font-size: 1.4rem;
  transform: rotate(-2deg);
}
.hero h1 {
  margin: 0 0 24px;
  color: var(--ink);
  font-size: clamp(44px, 6.2vw, 96px);
  line-height: 0.95;
}
.hero h1 span {
  display: block;
}
.hero h1 .line-white {
  color: white;
}
.highlight {
  width: fit-content;
  margin-top: 8px;
  padding: 2px 14px 0;
  background: var(--yellow);
  transform: rotate(-1.5deg);
}
.intro {
  max-width: 34em;
  margin: 0 0 32px;
  padding-left: 16px;
  border-left: 3px solid white;
  color: white;
  font-size: 1.1rem;
}
.hero-visual {
  position: relative;
}
/* Photo découpée en biais, avec un aplat bleu en dessous. */
.hero-visual::before {
  content: '';
  position: absolute;
  inset: auto -8% -24px 10%;
  height: 40%;
  background: var(--blue);
  transform: rotate(-3deg);
}
.hero-visual img {
  position: relative;
  display: block;
  width: 100%;
  height: auto;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  object-position: 55% center;
  clip-path: polygon(12% 0, 100% 3%, 98% 100%, 0 94%);
}
/* Ticket de programme, perforé à gauche. */
.ticket {
  position: absolute;
  right: -8px;
  bottom: -40px;
  width: 210px;
  padding: 14px 18px 14px 24px;
  background: var(--paper);
  border-left: 6px dotted var(--red);
  box-shadow: 4px 6px 0 rgb(0 0 0 / 0.35);
  transform: rotate(-5deg);
  font-size: 0.85rem;
}
.ticket-title {
  margin: 0 0 8px;
  font-size: 1.2rem;
  line-height: 1.2;
}
.ticket ul {
  margin: 0;
  padding: 0;
  list-style: none;
}
.ticket li {
  display: flex;
  gap: 16px;
  margin: 0;
  line-height: 1.5;
  border-bottom: 1px dashed #b9b09c;
}
.ticket-number {
  margin: 8px 0 0;
  color: var(--red);
  font-weight: 700;
  letter-spacing: 0.1em;
}

/* Valeurs : titre entouré d'un trait jaune, trois colonnes séparées. */
.values {
  display: grid;
  grid-template-columns: minmax(250px, 1fr) 3fr;
  gap: 48px;
  align-items: center;
  max-width: 1200px;
  margin: 0 auto;
  padding: 64px max(16px, 4vw);
}
.values h2,
.universes h2 {
  margin: 0;
  font-size: clamp(44px, 4.6vw, 68px);
  line-height: 0.9;
}
.values h2 span,
.universes h2 span {
  display: block;
}
.values h2 {
  position: relative;
  transform: rotate(-4deg);
}
.values h2::after {
  content: '';
  position: absolute;
  inset: -18px -28px -14px -20px;
  border: 4px solid var(--yellow);
  border-radius: 50%;
  transform: rotate(-6deg);
}
.values ul {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
  padding: 0;
  list-style: none;
}
.values li {
  margin: 0;
  padding: 0 24px;
  border-left: 2px solid var(--ink);
}
.values h3 {
  margin: 0 0 12px;
  font-size: 1.7rem;
}
.star {
  color: var(--red);
}
.mark {
  padding: 0 8px;
  background: linear-gradient(transparent 35%, var(--yellow) 35%);
}
.values p {
  margin: 0;
}

/* Univers : aplat bleu à gauche, photos inclinées avec légendes. */
.universes {
  display: grid;
  grid-template-columns: minmax(200px, 0.8fr) 3fr;
  gap: 32px;
  align-items: center;
  padding: 48px max(16px, 4vw) 24px;
  background: linear-gradient(100deg, var(--blue) 0 24%, transparent 24%);
}
.universes h2 {
  color: white;
}
.photos {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 32px;
}
figure {
  position: relative;
  margin: 0;
}
figure img {
  display: block;
  width: 100%;
  height: auto;
  border: 8px solid var(--ink);
  background: var(--ink);
}
.tilt-left {
  transform: rotate(-1.5deg);
}
.tilt-right {
  transform: rotate(1.5deg);
}
figcaption {
  position: absolute;
  left: 16px;
  bottom: -18px;
  max-width: 80%;
  padding: 6px 14px;
  background: var(--paper);
  border-bottom: 6px solid var(--yellow);
  font-size: clamp(1.1rem, 2vw, 1.6rem);
  line-height: 1.05;
  transform: rotate(-3deg);
}
.more {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 24px;
  margin: 40px 0 16px;
  font-weight: 700;
  font-size: 1.15rem;
}
.more::before,
.more::after {
  content: '';
  flex: 1;
  border-top: 2px solid var(--ink);
}
.more a {
  color: var(--ink);
  text-decoration-thickness: 2px;
}

/* Appel au devis : bandeau jaune, étoile rouge en décor. */
.cta {
  position: relative;
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 40px;
  align-items: center;
  padding: 72px max(16px, 4vw) 96px;
  background: var(--yellow);
}
.cta::before {
  content: '';
  position: absolute;
  top: -40px;
  right: 3%;
  width: 120px;
  height: 120px;
  background: var(--red);
  clip-path: polygon(
    50% 0,
    61% 30%,
    95% 20%,
    72% 47%,
    100% 70%,
    65% 68%,
    58% 100%,
    45% 72%,
    12% 90%,
    30% 58%,
    0 35%,
    35% 35%
  );
}
.cta h2 {
  margin: 0;
  font-size: clamp(48px, 7vw, 100px);
  line-height: 0.95;
  transform: rotate(-2deg);
}
.cta-text {
  padding-left: 24px;
  border-left: 2px solid var(--ink);
}
.cta-text p {
  margin: 0 0 24px;
  font-size: 1.1rem;
}
.cta-text strong {
  display: block;
}

/* Tablette et téléphone : une seule colonne, décors allégés. */
@media (max-width: 900px) {
  .hero,
  .values,
  .universes,
  .cta {
    grid-template-columns: minmax(0, 1fr);
  }
  .hero {
    padding-bottom: 104px;
  }
  .ticket {
    right: 0;
    bottom: -56px;
    width: 180px;
    font-size: 0.8rem;
  }
  .values h2 {
    justify-self: start;
    margin: 16px 0 8px 20px;
  }
  .values ul {
    grid-template-columns: minmax(0, 1fr);
    gap: 24px;
  }
  .universes {
    padding-top: 0;
    background: none;
  }
  .universes h2 {
    margin: 0 calc(-1 * max(16px, 4vw));
    padding: 32px max(16px, 4vw);
    background: var(--blue);
  }
  .more::before,
  .more::after {
    display: none;
  }
}
@media (max-width: 600px) {
  .photos {
    grid-template-columns: minmax(0, 1fr);
    gap: 48px;
  }
  .ticket {
    display: none;
  }
  .hero {
    padding-bottom: 72px;
  }
  .cta::before {
    width: 72px;
    height: 72px;
    top: -24px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .paper-button {
    transition: none;
  }
}
</style>

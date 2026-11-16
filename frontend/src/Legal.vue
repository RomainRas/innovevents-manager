<script setup>
// Pages légales (mentions légales, CGU, CGV, confidentialité) : une seule page, choisie par meta.legal de la route.
import { useRoute } from 'vue-router'

const page = useRoute().meta.legal
const updated = '14 novembre 2026'
</script>
<template>
  <main class="poster-page legal-page">
    <!-- Feuille de papier : avertissement de projet de formation, puis le texte légal -->
    <div class="sheet">
      <p class="legal-notice">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="32" height="32">
          <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2" />
          <path d="M12 11v6M12 7.5v.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
        </svg>
        <span>
          Projet réalisé dans le cadre d’une évaluation de formation (ECF Concepteur développeur d’applications).
          Innov’Events est une entreprise fictive : les informations de l’éditeur et les données de démonstration sont
          fictives.</span
        >
      </p>

      <!-- Mentions légales -->
      <template v-if="page === 'mentions'">
        <h1>Mentions légales</h1>
        <h2>Éditeur du site</h2>
        <p>
          Innov’Events, société par actions simplifiée (SAS) au capital social de 10 000 €<br />
          Siège social : 12 rue des Événements, 69002 Lyon, France<br />
          Courriel : <a href="mailto:contact@rascleromain.fr">contact@rascleromain.fr</a><br />
          Téléphone : 04 00 00 00 00
        </p>
        <h2>Responsable de la publication</h2>
        <p>Chloé, présidente et cofondatrice d’Innov’Events.</p>
        <h2>Hébergeur</h2>
        <p>
          Hostinger International Ltd, société à responsabilité limitée de droit chypriote<br />
          61 Lordou Vironos Street, 6023 Larnaca, Chypre<br />
          Téléphone : +370 645 03378<br />
          Serveur situé en France.
        </p>
        <h2>Propriété intellectuelle</h2>
        <p>
          Les textes, illustrations et éléments graphiques de ce site sont la propriété d’Innov’Events. Les
          illustrations ont été créées spécifiquement pour ce site. Toute reproduction sans autorisation est interdite.
        </p>
        <h2>Données personnelles</h2>
        <p>
          Le traitement de vos données est décrit dans la
          <RouterLink to="/confidentialite">politique de confidentialité</RouterLink>.
        </p>
      </template>

      <template v-else-if="page === 'cgu'">
        <!-- Conditions générales d'utilisation -->
        <h1>Conditions générales d’utilisation</h1>
        <p>Dernière mise à jour : {{ updated }}.</p>
        <h2>1. Objet</h2>
        <p>
          Les présentes conditions encadrent l’utilisation du site et de l’application Innov’Events Manager :
          consultation des événements publiés et des avis, demande de devis, contact, espace client, espaces de l’équipe
          et application mobile réservée à l’équipe.
        </p>
        <h2>2. Accès</h2>
        <p>
          Les pages publiques sont accessibles à tous. Les espaces client, employé et administrateur nécessitent un
          compte. Les comptes administrateur ne peuvent pas être créés depuis l’application.
        </p>
        <h2>3. Compte utilisateur</h2>
        <p>
          Le mot de passe comporte au moins 8 caractères, dont une majuscule, une minuscule, un chiffre et un caractère
          spécial. Il est personnel et confidentiel. Un mot de passe provisoire (création par l’agence ou mot de passe
          oublié) doit être changé à la connexion suivante. Signalez sans délai toute utilisation non autorisée via la
          page <RouterLink to="/contact">Contact</RouterLink>.
        </p>
        <h2>4. Engagements de l’utilisateur</h2>
        <p>
          L’utilisateur fournit des informations exactes et s’interdit tout usage frauduleux, toute tentative d’accès
          non autorisé et toute publication de contenu illicite, injurieux ou portant atteinte aux droits de tiers.
        </p>
        <h2>5. Avis</h2>
        <p>
          Un client peut donner son avis sur un événement terminé depuis son espace. Chaque avis est relu par l’équipe
          avant publication et peut être refusé s’il est contraire aux présentes conditions. Seuls le prénom et
          l’initiale du nom de l’auteur sont publiés.
        </p>
        <h2>6. Publication des événements</h2>
        <p>
          Un événement n’apparaît sur la page Événements qu’avec l’accord du client. Les montants ne sont jamais
          publiés.
        </p>
        <h2>7. Disponibilité et responsabilité</h2>
        <p>
          Innov’Events s’efforce d’assurer l’accès au service en continu, sans garantie d’absence d’interruption,
          notamment pour maintenance. Elle peut suspendre un compte en cas de manquement aux présentes conditions.
        </p>
        <h2>8. Données personnelles</h2>
        <p>
          Voir la <RouterLink to="/confidentialite">politique de confidentialité</RouterLink>. Chaque client peut
          demander la suppression de son compte et de ses données.
        </p>
        <h2>9. Droit applicable</h2>
        <p>
          Les présentes conditions sont soumises au droit français. À défaut d’accord amiable, les tribunaux français
          sont compétents.
        </p>
      </template>

      <template v-else-if="page === 'cgv'">
        <!-- Conditions générales de vente -->
        <h1>Conditions générales de vente</h1>
        <p>Dernière mise à jour : {{ updated }}.</p>
        <h2>1. Champ d’application</h2>
        <p>
          Les présentes conditions s’appliquent aux prestations d’organisation d’événements professionnels (séminaires,
          conférences, soirées d’entreprise et autres) proposées par Innov’Events à des clients professionnels.
        </p>
        <h2>2. Demande et devis</h2>
        <p>
          Toute prestation fait l’objet d’un devis personnalisé établi après échange avec le client. Le devis détaille
          chaque prestation avec son montant hors taxes, la TVA et le total toutes taxes comprises. Il est transmis par
          courriel ou mis à disposition dans l’espace client.
        </p>
        <h2>3. Acceptation, modification et refus</h2>
        <p>
          Depuis son espace, le client accepte le devis, le refuse ou demande une modification en précisant son motif.
          Une demande de modification peut donner lieu à une contre-proposition qui remplace la version précédente.
          L’acceptation de la version en vigueur vaut commande.
        </p>
        <h2>4. Prix et paiement</h2>
        <p>
          Les prix sont exprimés en euros hors taxes, majorés de la TVA au taux en vigueur. Sauf mention contraire au
          devis, un acompte de 30 % est dû à l’acceptation et le solde à réception de la facture, au plus tard 30 jours
          après l’événement. Tout retard de paiement entraîne des pénalités au taux légal en vigueur et une indemnité
          forfaitaire de 40 € pour frais de recouvrement (article L. 441-10 du Code de commerce).
        </p>
        <h2>5. Annulation</h2>
        <p>
          Toute annulation par le client est notifiée par écrit. Les sommes engagées auprès des prestataires à la date
          d’annulation restent dues, dans la limite du montant du devis accepté.
        </p>
        <h2>6. Obligations d’Innov’Events</h2>
        <p>
          Innov’Events est tenue d’une obligation de moyens dans la coordination des prestataires et la réalisation de
          l’événement conforme au devis accepté.
        </p>
        <h2>7. Force majeure</h2>
        <p>
          Aucune partie n’est responsable d’un manquement dû à un cas de force majeure au sens de l’article 1218 du Code
          civil.
        </p>
        <h2>8. Réclamations et droit applicable</h2>
        <p>
          Les réclamations sont adressées via la page <RouterLink to="/contact">Contact</RouterLink>. Les présentes
          conditions sont soumises au droit français ; le tribunal de commerce de Lyon est compétent à défaut d’accord
          amiable.
        </p>
      </template>

      <template v-else>
        <!-- Politique de confidentialité -->
        <h1>Politique de confidentialité</h1>
        <p>
          Dernière mise à jour : {{ updated }}. Responsable du traitement : Innov’Events (voir les
          <RouterLink to="/mentions-legales">mentions légales</RouterLink>).
        </p>
        <h2>Données traitées et finalités</h2>
        <ul>
          <li>
            <strong>Demande de devis</strong> : entreprise, nom, prénom, adresse électronique, téléphone et informations
            sur l’événement, pour vous recontacter et établir un devis (mesures précontractuelles).
          </li>
          <li>
            <strong>Compte et espace client</strong> : identité, adresse électronique, nom d’utilisateur et mot de passe
            chiffré, pour gérer vos devis et événements (exécution du contrat).
          </li>
          <li>
            <strong>Contact</strong> : nom d’utilisateur ou adresse électronique, titre et message, transmis par
            courriel à l’agence pour vous répondre (intérêt légitime).
          </li>
          <li>
            <strong>Sécurité</strong> : adresse IP lors des connexions réussies et des tentatives échouées, avec
            l’adresse électronique saisie, pour protéger les comptes et détecter les abus (intérêt légitime).
          </li>
        </ul>
        <h2>Destinataires</h2>
        <p>
          Seule l’équipe Innov’Events accède à vos données, selon son rôle. Elles sont hébergées en France par notre
          hébergeur et ne sont ni vendues ni transmises à des tiers à des fins commerciales.
        </p>
        <h2>Durées de conservation</h2>
        <p>
          Prospects sans suite : 3 ans après le dernier contact. Clients : durée de la relation commerciale, puis durées
          légales (10 ans pour les pièces comptables). Messages de contact : le temps de traiter la demande.
        </p>
        <h2>Cookies</h2>
        <p>
          Le site utilise uniquement un cookie de session strictement nécessaire à la connexion et à la protection
          contre les requêtes frauduleuses. Aucun cookie publicitaire ou de mesure d’audience n’est déposé.
        </p>
        <h2>Vos droits</h2>
        <p>
          Vous disposez des droits d’accès, de rectification, d’effacement, de limitation, d’opposition et de
          portabilité. Exercez-les via la page <RouterLink to="/contact">Contact</RouterLink>. Vous pouvez saisir la
          CNIL (www.cnil.fr) si vous estimez que vos droits ne sont pas respectés.
        </p>
      </template>
    </div>
  </main>
</template>
<style scoped>
/* Pages légales en style affiche : feuille claire, titre souligné de rouge, rubriques à filet coloré. */
.legal-page {
  padding-inline: max(16px, calc((100% - 960px) / 2));
}
.sheet {
  padding: 32px clamp(20px, 4vw, 40px) 40px;
  background: #fbf8f1;
}
.legal-notice {
  display: flex;
  gap: 20px;
  align-items: center;
  margin: 0 0 32px;
  padding: 16px 24px;
  background: #fbe8a6;
  border-radius: 4px;
  font-family: Georgia, 'Times New Roman', serif;
  line-height: 1.45;
}
.legal-notice svg {
  flex: none;
}
.legal-notice span {
  padding-left: 20px;
  border-left: 2px solid #111;
}
h1 {
  margin: 0 0 32px;
  padding-bottom: 14px;
  border-bottom: 8px solid #c8221a;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-weight: 400;
  font-size: clamp(44px, 7vw, 76px);
  line-height: 1.05;
  text-transform: uppercase;
  color: #111;
}
h2 {
  display: flex;
  align-items: center;
  gap: 20px;
  margin: 40px 0 12px;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-weight: 400;
  font-size: clamp(26px, 3.4vw, 36px);
  line-height: 1.1;
  text-transform: uppercase;
}
/* Filet coloré après chaque rubrique : bleu, jaune puis rouge. */
h2::after {
  content: '';
  flex: 1;
  min-width: 40px;
  border-top: 5px solid #1d3fbf;
}
h2:nth-of-type(3n + 2)::after {
  border-color: #ffd836;
}
h2:nth-of-type(3n)::after {
  border-color: #c8221a;
}
@media (max-width: 600px) {
  .legal-notice {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  .legal-notice span {
    padding-left: 0;
    border-left: 0;
  }
}
p,
li {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.05rem;
  line-height: 1.6;
}
</style>

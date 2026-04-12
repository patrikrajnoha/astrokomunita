const appName = String(import.meta.env.VITE_APP_NAME || 'Astrokomunita').trim() || 'Astrokomunita'
const controllerName = String(import.meta.env.VITE_LEGAL_CONTROLLER_NAME || appName).trim() || appName

const sharedFacts = {
  appName,
  controllerName,
}

export function getLegalPageContent(kind) {
  if (kind === 'privacy') {
    return {
      title: 'Privacy Policy',
      eyebrow: 'Legal',
      intro: `${appName} spracúva osobné údaje len v rozsahu potrebnom na fungovanie účtu, komunity a bezpečnosti platformy.`,
      sections: [
        {
          heading: 'Prevádzkovateľ',
          paragraphs: [
            `Prevádzkovateľom platformy je ${controllerName}. Tento text je praktický prehľad aktuálneho fungovania aplikácie.`,
          ],
        },
        {
          heading: 'Aké údaje spracúvame',
          paragraphs: [
            'Údaje o účte a profile, ktoré vložíš pri používaní platformy (napr. username, profilové nastavenia a obsah profilu).',
            'Komunitný obsah, ktorý vytvoríš alebo upravíš (príspevky, komentáre, reakcie, uložené položky a podobné akcie v aplikácii).',
            'Technické a bezpečnostné metadáta potrebné na prihlásenie, ochranu formulárov a prevenciu zneužitia služby.',
          ],
        },
        {
          heading: 'Prečo údaje používame',
          paragraphs: [
            'Na prevádzku účtu, zobrazenie funkcionalít komunity, doručenie obsahu a personalizáciu funkcií, ktoré si sám zapneš.',
            'Na bezpečnosť platformy, ochranu pred zneužitím a riešenie technických incidentov.',
          ],
        },
        {
          heading: 'Ako dlho údaje držíme',
          paragraphs: [
            'Údaje naviazané na účet sa držia počas existencie účtu, pokiaľ ich neodstrániš skôr priamo v aplikácii.',
            'Session dáta, notifikácie a technické logy majú obmedzenú dobu uchovania podľa aktívnej serverovej konfigurácie.',
            'Po zrušení účtu prebehne vymazanie alebo anonymizácia naviazaných dát v rozsahu aktuálneho správania aplikácie.',
          ],
        },
        {
          heading: 'Tvoje možnosti',
          paragraphs: [
            'V nastaveniach si vieš upraviť profil, stiahnuť export dát a požiadať o odstránenie účtu.',
          ],
        },
      ],
      facts: sharedFacts,
    }
  }

  if (kind === 'terms') {
    return {
      title: 'Terms of Service',
      eyebrow: 'Legal',
      intro: `${appName} je komunitná platforma pre astronomický obsah a sociálne funkcie. Používaním služby súhlasíš s týmito pravidlami.`,
      sections: [
        {
          heading: 'Základné pravidlá používania',
          paragraphs: [
            'Používaj platformu zákonným spôsobom, s rešpektom k ostatným a bez pokusov o narušenie bezpečnosti alebo dostupnosti služby.',
            'Údaje v profile a komunitnom obsahu majú byť pravdivé a nesmú porušovať práva tretích strán.',
          ],
        },
        {
          heading: 'Obsah a zodpovednosť',
          paragraphs: [
            'Za obsah zverejnený cez svoj účet zodpovedáš ty. Obsah nesmie obsahovať spam, podvod, nelegálny materiál ani škodlivé zavádzanie.',
            'Platforma môže pri porušení pravidiel obmedziť viditeľnosť obsahu, dočasne obmedziť účet alebo ukončiť prístup.',
          ],
        },
        {
          heading: 'Bezpečnosť a ochrana služby',
          paragraphs: [
            'Na ochranu prihlásenia a formulárov používame session mechanizmy, CSRF ochranu a bezpečnostné overenie pri registrácii.',
          ],
        },
        {
          heading: 'Dostupnosť a zmeny',
          paragraphs: [
            'Služba je priebežne vyvíjaná. Funkcie sa môžu meniť, pridávať alebo odoberať podľa technických a bezpečnostných potrieb.',
            'Tieto podmienky môžeme primerane aktualizovať, ak sa zmení fungovanie platformy alebo legislatívne požiadavky.',
          ],
        },
      ],
      facts: sharedFacts,
    }
  }

  return {
    title: 'Cookies',
    eyebrow: 'Legal',
    intro: `${appName} používa minimálny rozsah cookies a podobných technológií, ktoré sú potrebné na bezpečné fungovanie prihlásenia a formulárov.`,
    sections: [
      {
        heading: 'Nevyhnutné cookies',
        paragraphs: [
          'Používame nevyhnutné cookies pre reláciu používateľa a ochranu API požiadaviek (session cookie a XSRF-TOKEN).',
          'Tieto cookies sú potrebné na to, aby prihlásenie, formulárové akcie a bezpečnostné mechanizmy fungovali správne.',
        ],
      },
      {
        heading: 'Bezpečnostné overenie',
        paragraphs: [
          'Pri registrácii využívame Cloudflare Turnstile proti botom. Tento mechanizmus môže pracovať s technickými identifikátormi potrebnými na ochranu formulára.',
        ],
      },
      {
        heading: 'Čo nepoužívame',
        paragraphs: [
          'Podľa aktuálnej implementácie frontendu nepoužívame reklamné ani analytické cookies tretích strán.',
        ],
      },
      {
        heading: 'Správa preferencií',
        paragraphs: [
          'Cookies môžeš spravovať v prehliadači. Vypnutie nevyhnutných cookies môže obmedziť alebo znemožniť prihlásenie do aplikácie.',
        ],
      },
    ],
    facts: sharedFacts,
  }
}

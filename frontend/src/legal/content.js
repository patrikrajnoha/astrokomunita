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
      intro: `${appName} spracuva osobne udaje len v rozsahu potrebnom na fungovanie uctu, komunity a bezpecnosti platformy.`,
      sections: [
        {
          heading: 'Prevadzkovatel',
          paragraphs: [
            `Prevadzkovatelom platformy je ${controllerName}. Tento text je prakticky prehlad aktualneho fungovania aplikacie.`,
          ],
        },
        {
          heading: 'Ake udaje spracuvame',
          paragraphs: [
            'Udaje o ucte a profile, ktore vlozis pri pouzivani platformy (napr. username, profilove nastavenia a obsah profilu).',
            'Komunitny obsah, ktory vytvoris alebo upravis (prispevky, komentare, reakcie, ulozene polozky a podobne akcie v aplikacii).',
            'Technicke a bezpecnostne metadata potrebne na prihlasenie, ochranu formularov a prevenciu zneuzitia sluzby.',
          ],
        },
        {
          heading: 'Preco udaje pouzivame',
          paragraphs: [
            'Na prevadzku uctu, zobrazenie funkcionalit komunity, dorucenie obsahu a personalizaciu funkcii, ktore si sam zapnes.',
            'Na bezpecnost platformy, ochranu pred zneuzitim a riesenie technickych incidentov.',
          ],
        },
        {
          heading: 'Ako dlho udaje drzime',
          paragraphs: [
            'Udaje naviazane na ucet sa drzia pocas existencie uctu, pokial ich neodstranis skorej priamo v aplikacii.',
            'Session data, notifikacie a technicke logy maju obmedzenu dobu uchovania podla aktivnej serverovej konfiguracie.',
            'Po zruseni uctu prebehne vymazanie alebo anonymizacia naviazanych dat v rozsahu aktualneho spravania aplikacie.',
          ],
        },
        {
          heading: 'Tvoje moznosti',
          paragraphs: [
            'V nastaveniach si vies upravit profil, stiahnut export dat a poziadat o odstranenie uctu.',
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
      intro: `${appName} je komunitna platforma pre astronomicky obsah a socialne funkcie. Pouzivanim sluzby suhlasis s tymito pravidlami.`,
      sections: [
        {
          heading: 'Zakladne pravidla pouzivania',
          paragraphs: [
            'Pouzivaj platformu zakonny sposobom, s respektom k ostatnym a bez pokusov o narusenie bezpecnosti alebo dostupnosti sluzby.',
            'Udaje v profile a komunitnom obsahu maju byt pravdive a nesmu porusovat prava tretich stran.',
          ],
        },
        {
          heading: 'Obsah a zodpovednost',
          paragraphs: [
            'Za obsah zverejneny cez svoj ucet zodpovedas ty. Obsah nesmie obsahovat spam, podvod, nelegalny material ani skodlive zavadzanie.',
            'Platforma moze pri poruseni pravidiel obmedzit viditelnost obsahu, docasne obmedzit ucet alebo ukoncit pristup.',
          ],
        },
        {
          heading: 'Bezpecnost a ochrana sluzby',
          paragraphs: [
            'Na ochranu prihlasenia a formularov pouzivame session mechanizmy, CSRF ochranu a bezpecnostne overenie pri registracii.',
          ],
        },
        {
          heading: 'Dostupnost a zmeny',
          paragraphs: [
            'Sluzba je priebezne vyvijana. Funkcie sa mozu menit, pridavat alebo odoberat podla technickych a bezpecnostnych potrieb.',
            'Tieto podmienky mozeme primerane aktualizovat, ak sa zmeni fungovanie platformy alebo legislativne poziadavky.',
          ],
        },
      ],
      facts: sharedFacts,
    }
  }

  return {
    title: 'Cookies',
    eyebrow: 'Legal',
    intro: `${appName} pouziva minimalny rozsah cookies a podobnych technologii, ktore su potrebne na bezpecne fungovanie prihlasenia a formularov.`,
    sections: [
      {
        heading: 'Nevyhnutne cookies',
        paragraphs: [
          'Pouzivame nevyhnutne cookies pre relaciu pouzivatela a ochranu API poziadaviek (session cookie a XSRF-TOKEN).',
          'Tieto cookies su potrebne na to, aby prihlasenie, formularove akcie a bezpecnostne mechanizmy fungovali spravne.',
        ],
      },
      {
        heading: 'Bezpecnostne overenie',
        paragraphs: [
          'Pri registracii vyuzivame Cloudflare Turnstile proti botom. Tento mechanizmus moze pracovat s technickymi identifikatormi potrebnymi na ochranu formulara.',
        ],
      },
      {
        heading: 'Co nepouzivame',
        paragraphs: [
          'Podla aktualnej implementacie frontendu nepouzivame reklamne ani analyticke cookies tretich stran.',
        ],
      },
      {
        heading: 'Sprava preferencii',
        paragraphs: [
          'Cookies mozes spravovat v prehliadaci. Vypnutie nevyhnutnych cookies moze obmedzit alebo znemoznit prihlasenie do aplikacie.',
        ],
      },
    ],
    facts: sharedFacts,
  }
}

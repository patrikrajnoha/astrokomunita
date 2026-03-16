const appName = String(import.meta.env.VITE_APP_NAME || 'Astrokomunita').trim() || 'Astrokomunita'
const controllerName = String(import.meta.env.VITE_LEGAL_CONTROLLER_NAME || appName).trim() || appName
const contactEmail =
  String(import.meta.env.VITE_LEGAL_CONTACT_EMAIL || import.meta.env.VITE_CONTACT_EMAIL || 'hello@example.com').trim() ||
  'hello@example.com'

const parsePositiveNumber = (value, fallback) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
}

const logRetentionDays = parsePositiveNumber(import.meta.env.VITE_LOG_RETENTION_DAYS, 30)
const notificationRetentionDays = parsePositiveNumber(import.meta.env.VITE_NOTIFICATION_RETENTION_DAYS, 90)
const sessionLifetimeHours = parsePositiveNumber(import.meta.env.VITE_SESSION_LIFETIME_HOURS, 2)

const sharedFacts = {
  appName,
  controllerName,
  contactEmail,
  logRetentionDays,
  notificationRetentionDays,
  sessionLifetimeHours,
}

export function getLegalPageContent(kind) {
  if (kind === 'privacy') {
    return {
      title: 'Privacy Policy',
      eyebrow: 'Legal',
      intro: `${appName} spracúva osobné údaje v minimálnom rozsahu potrebnom na účet, komunitné funkcie a bezpečnosť platformy. Toto je praktický, minimálny text a nie právne poradenstvo.`,
      sections: [
        {
          heading: 'Prevádzkovateľ a kontakt',
          paragraphs: [
            `Prevádzkovateľom osobných údajov je ${controllerName}. Pre otázky o súkromí, exporte alebo výmaze účtu nás môžeš kontaktovať na ${contactEmail}.`,
          ],
        },
        {
          heading: 'Aké údaje spracúvame',
          paragraphs: [
            'Spracúvame údaje o účte a profile, najmä meno, username, email, profilový obsah, nastavenia, preferencie a obsah, ktorý sám vytvoríš.',
            'Ak používaš lokalitu, spracúvame približnú lokalitu a súvisiace preferencie pre personalizáciu obsahu, kalendár a observing funkcie. Session systém môže dočasne uchovať aj IP adresu a user-agent na bezpečnosť a udržanie relácie.',
            'Pri registrácii používame Cloudflare Turnstile ako bezpečnostný mechanizmus proti botom. Pri emailovej komunikácii môžu byť spracúvané údaje potrebné na verifikáciu emailu, systémové notifikácie a newsletter, ak si ho zapol.',
          ],
        },
        {
          heading: 'Účely a právne základy',
          paragraphs: [
            'Účely spracovania zahŕňajú vytvorenie a správu účtu, prevádzku profilu a komunity, zabezpečenie prihlásenia a CSRF ochrany, prevenciu zneužitia a doručovanie nevyhnutných emailov.',
            'Právnym základom je plnenie zmluvy pri účte a poskytovaní služby, oprávnený záujem pri bezpečnosti, prevencii zneužitia a technických logoch, a súhlas tam, kde je voliteľná funkcionalita založená na prihlásení k odberu alebo podobnej voľbe.',
          ],
        },
        {
          heading: 'Retention a mazanie',
          paragraphs: [
            `Aktívne session dáta sa bežne držia počas trvania relácie; štandardný session timeout je približne ${sessionLifetimeHours} hodiny a databázové sessions sa priebežne čistia cez session prune alebo garbage collection. IP adresa v sessions môže byť dočasne uložená pre bezpečnosť.`,
            `Aplikačné logy sa uchovávajú len obmedzene dlho podľa infraštruktúry, cielene približne ${logRetentionDays} dní, ak nie je potrebné dlhšie uchovanie pre riešenie incidentu alebo splnenie povinnosti.`,
            `In-app notifikácie sú navrhnuté na automatické mazanie po približne ${notificationRetentionDays} dňoch. Pri zrušení účtu sa odstránia aj naviazané dáta a obsah podľa aktuálneho správania aplikácie.`,
          ],
        },
        {
          heading: 'Tvoje práva',
          paragraphs: [
            'Máš právo požiadať o prístup k svojim údajom, opravu, obmedzenie, námietku podľa povahy spracovania a vymazanie. Priamo v aplikácii máš dostupný export údajov, úpravu profilu a self-service odstránenie účtu.',
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
      intro: `${appName} je komunitná služba pre astronomický obsah, udalosti a sociálne funkcie. Tieto podmienky sú jednoduchý minimálny základ pre bežné používanie platformy.`,
      sections: [
        {
          heading: 'Používanie služby',
          paragraphs: [
            `Používaním ${appName} súhlasíš s tým, že budeš poskytovať pravdivé údaje o účte, nebudeš zneužívať platformu a budeš rešpektovať komunitné pravidlá, zákon a práva iných osôb.`,
            'Služba môže obsahovať komunitný obsah, pozvánky, notifikácie, observing nástroje a emailové správy spojené s prevádzkou účtu.',
          ],
        },
        {
          heading: 'Obsah a účet',
          paragraphs: [
            'Zodpovedáš za obsah, ktorý publikuješ, zdieľaš alebo posielaš cez svoj účet. Obsah nesmie porušovať práva tretích strán, obsahovať spam, zavádzajúce informácie alebo nezákonný materiál.',
            'Vyhradzujeme si právo obmedziť alebo ukončiť prístup pri zjavnom zneužití, bezpečnostnom incidente alebo porušení pravidiel.',
          ],
        },
        {
          heading: 'Súkromie a bezpečnosť',
          paragraphs: [
            `Detaily o osobných údajoch, retention a právach nájdeš na stránke Privacy Policy. Na bezpečnosť registrácie a prevádzky služby môžeme používať session mechanizmy, CSRF ochranu a Cloudflare Turnstile.`,
          ],
        },
        {
          heading: 'Dostupnosť a zmeny',
          paragraphs: [
            'Služba sa poskytuje priebežne a môže sa meniť, dočasne obmedziť alebo aktualizovať bez nároku na nepretržitú dostupnosť. Vybrané funkcie môžu byť pridané, odstránené alebo upravené z technických a bezpečnostných dôvodov.',
          ],
        },
        {
          heading: 'Kontakt',
          paragraphs: [
            `Pre otázky k podmienkam alebo prevádzke služby napíš na ${contactEmail}.`,
          ],
        },
      ],
      facts: sharedFacts,
    }
  }

  return {
    title: 'Cookies',
    eyebrow: 'Legal',
    intro: `${appName} používa minimálny cookies surface potrebný na prihlásenie, bezpečnosť a ochranu formulárov.`,
    sections: [
      {
        heading: 'Nevyhnutné cookies a podobné technológie',
        paragraphs: [
          'Používame len nevyhnutné cookies pre fungovanie aplikácie, konkrétne session cookie pre reláciu používateľa a XSRF-TOKEN pre ochranu formulárov a API požiadaviek.',
          'Pri registrácii používame Cloudflare Turnstile ako bezpečnostný mechanizmus proti botom. Tento mechanizmus môže pracovať s vlastnými technickými identifikátormi alebo signálmi potrebnými na bezpečnosť formulára.',
        ],
      },
      {
        heading: 'Čo momentálne nepoužívame',
        paragraphs: [
          'Podľa aktuálneho stavu nepoužívame analytics ani marketing cookies. Ak sa to v budúcnosti zmení, táto stránka by mala byť aktualizovaná spolu s príslušným consent flow.',
        ],
      },
      {
        heading: 'Retention a sessions',
        paragraphs: [
          `Session cookie slúži na udržanie prihlásenia a štandardne sa viaže na reláciu so session timeoutom približne ${sessionLifetimeHours} hodiny. Databázové sessions sa čistia priebežne cez session prune alebo garbage collection a môžu dočasne obsahovať IP adresu pre bezpečnosť.`,
          `In-app notifikácie sa mažú po približne ${notificationRetentionDays} dňoch. Aplikačné logy majú mať obmedzenú retention, cielene približne ${logRetentionDays} dní.`,
        ],
      },
      {
        heading: 'Kontakt a voľby',
        paragraphs: [
          `Ak máš otázky ku cookies alebo súkromiu, napíš na ${contactEmail}. Svoje konto, export dát a vymazanie účtu spravuješ priamo v nastaveniach aplikácie.`,
        ],
      },
    ],
    facts: sharedFacts,
  }
}

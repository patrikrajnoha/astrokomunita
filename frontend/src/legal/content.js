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
      intro: `${appName} spracuva osobne udaje v minimalnom rozsahu potrebnom na ucet, komunitne funkcie a bezpecnost platformy. Toto je prakticky, minimalny text a nie pravne poradenstvo.`,
      sections: [
        {
          heading: 'Prevadzkovatel a kontakt',
          paragraphs: [
            `Prevadzkovatelom osobnych udajov je ${controllerName}. Pre otazky o sukromi, exporte alebo vymaze uctu nas mozes kontaktovat na ${contactEmail}.`,
          ],
        },
        {
          heading: 'Ake udaje spracuvame',
          paragraphs: [
            'Spracuvame udaje o ucte a profile, najma meno, username, email, profilovy obsah, nastavenia, preferencie a obsah, ktory sam vytvoris.',
            'Ak pouzivas lokalitu, spracuvame pribliznu lokalitu a suvisiace preference pre personalizaciu obsahu, kalendar a observing funkcie. Session system moze docasne uchovat aj IP adresu a user-agent na bezpecnost a udrzanie relacie.',
            'Pri registracii pouzivame Cloudflare Turnstile ako bezpecnostny mechanizmus proti botom. Pri emailovej komunikacii mozu byt spracuvane udaje potrebne na verifikaciu emailu, systemove notifikacie a newsletter, ak si ho zapol.',
          ],
        },
        {
          heading: 'Ucely a pravne zaklady',
          paragraphs: [
            'Ucely spracovania zaharnaju vytvorenie a spravu uctu, prevadzku profilu a komunity, zabezpecenie prihlasenia a CSRF ochrany, prevenciu zneuzitia a dorucovanie nevyhnutnych emailov.',
            'Pravnym zakladom je plnenie zmluvy pri ucte a poskytovani sluzby, opravneny zaujem pri bezpecnosti, prevencii zneuzitia a technickych logoch, a suhlas tam, kde je volitelna funkcionalita zalozena na prihlaseni k odberu alebo podobnej volbe.',
          ],
        },
        {
          heading: 'Retention a mazanie',
          paragraphs: [
            `Aktivne session data sa bezne drzia pocas trvania relacie; standardny session timeout je priblizne ${sessionLifetimeHours} hodiny a databazove sessions sa priebezne cistia cez session prune alebo garbage collection. IP adresa v sessions moze byt docasne ulozena pre bezpecnost.`,
            `Aplikacne logy sa uchovavaju len obmedzene dlho podla infrastruktury, cielene priblizne ${logRetentionDays} dni, ak nie je potrebne dlhsie uchovanie pre riesenie incidentu alebo splnenie povinnosti.`,
            `In-app notifikacie su navrhnute na automaticke mazanie po približne ${notificationRetentionDays} dnoch. Pri zruseni uctu sa odstrania aj naviazane data a obsah podla aktualneho spravania aplikacie.`,
          ],
        },
        {
          heading: 'Tvoje prava',
          paragraphs: [
            'Mas pravo poziadat o pristup k svojim udajom, opravu, obmedzenie, namietku podla povahy spracovania a vymazanie. Priamo v aplikacii mas dostupny export udajov, upravu profilu a self-service odstranenie uctu.',
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
      intro: `${appName} je komunitna sluzba pre astronomicky obsah, udalosti a socialne funkcie. Tieto podmienky su jednoduchy minimalny zaklad pre bezne pouzivanie platformy.`,
      sections: [
        {
          heading: 'Pouzivanie sluzby',
          paragraphs: [
            `Pouzivanim ${appName} suhlasis s tym, ze budes poskytovat pravdive udaje o ucte, nebudes zneuzivat platformu a budes respektovat komunitne pravidla, zakon a prava inych osob.`,
            'Sluzba moze obsahovat komunitny obsah, pozvanky, notifikacie, observing nastroje a emailove spravy spojene s prevadzkou uctu.',
          ],
        },
        {
          heading: 'Obsah a ucet',
          paragraphs: [
            'Zodpovedas za obsah, ktory publikujes, zdielas alebo posielas cez svoj ucet. Obsah nesmie porusovat prava tretich stran, obsahovat spam, zavadzajuce informacie alebo nezakonny material.',
            'Vyhradzujeme si pravo obmedzit alebo ukoncit pristup pri zjavnom zneuziti, bezpecnostnom incidente alebo poruseni pravidiel.',
          ],
        },
        {
          heading: 'Sukromie a bezpecnost',
          paragraphs: [
            `Detaily o osobnych udajoch, retention a pravach najdes na stranke Privacy Policy. Na bezpecnost registracie a prevadzky sluzby mozeme pouzivat session mechanizmy, CSRF ochranu a Cloudflare Turnstile.`,
          ],
        },
        {
          heading: 'Dostupnost a zmeny',
          paragraphs: [
            'Sluzba sa poskytuje priebezne a moze sa menit, docasne obmedzit alebo aktualizovat bez naroku na nepretrzitu dostupnost. Vybrane funkcie mozu byt pridane, odstranene alebo upravene z technickych a bezpecnostnych dovodov.',
          ],
        },
        {
          heading: 'Kontakt',
          paragraphs: [
            `Pre otazky k podmienkam alebo prevadzke sluzby napis na ${contactEmail}.`,
          ],
        },
      ],
      facts: sharedFacts,
    }
  }

  return {
    title: 'Cookies',
    eyebrow: 'Legal',
    intro: `${appName} pouziva minimalny cookies surface potrebny na prihlasenie, bezpecnost a ochranu formularov.`,
    sections: [
      {
        heading: 'Nevyhnutne cookies a podobne technologie',
        paragraphs: [
          'Pouzivame len nevyhnutne cookies pre fungovanie aplikacie, konkretne session cookie pre relaciu pouzivatela a XSRF-TOKEN pre ochranu formularov a API poziadaviek.',
          'Pri registracii pouzivame Cloudflare Turnstile ako bezpecnostny mechanizmus proti botom. Tento mechanizmus moze pracovat s vlastnymi technickymi identifikatori alebo signalmi potrebnymi na bezpecnost formulara.',
        ],
      },
      {
        heading: 'Co momentalne nepouzivame',
        paragraphs: [
          'Podla aktualneho stavu nepouzivame analytics ani marketing cookies. Ak sa to v buducnosti zmeni, tato stranka by mala byt aktualizovana spolu s prislusnym consent flow.',
        ],
      },
      {
        heading: 'Retention a sessions',
        paragraphs: [
          `Session cookie sluzi na udrzanie prihlasenia a standardne sa viaze na relaciu so session timeoutom priblizne ${sessionLifetimeHours} hodiny. Databazove sessions sa cistia priebezne cez session prune alebo garbage collection a mozu docasne obsahovat IP adresu pre bezpecnost.`,
          `In-app notifikacie sa mazu po priblizne ${notificationRetentionDays} dnoch. Aplikacne logy maju mat obmedzenu retention, cielene priblizne ${logRetentionDays} dni.`,
        ],
      },
      {
        heading: 'Kontakt a volby',
        paragraphs: [
          `Ak mas otazky ku cookies alebo sukromiu, napis na ${contactEmail}. Svoje konto, export dat a vymazanie uctu spravujes priamo v nastaveniach aplikacie.`,
        ],
      },
    ],
    facts: sharedFacts,
  }
}

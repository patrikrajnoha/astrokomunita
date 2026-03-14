export const performanceMetricsMessages = {
  sk: {
    common: {
      na: '-',
      yes: 'Ano',
      no: 'Nie',
    },
    units: {
      ms: 'ms',
      requests: 'poziadaviek',
    },
    page: {
      title: 'Vykonnostne metriky',
      subtitle: 'Serverovy benchmark panel pre overenie staging/dev prostredia.',
    },
    cards: {
      warningTitle: 'Upozornenie:',
      warningText: 'Spustenie benchmarku je urcene pre staging/dev a moze docasne zvysit zataz.',
      runTitle: 'Spustenie benchmarku',
      runDescription: 'Nastav parametre merania a spusti benchmark.',
      resultsTitle: 'Najnovsie vysledky',
      resultsDescription: 'Porovnaj posledne behy podla klucovych metrik.',
      resultsMeta: '{{count}} zaznamov, posledny beh: {{latest}}',
      resultsMetaEmpty: 'Zatial nie su ulozene ziadne zaznamy benchmarku.',
      detailTitle: 'Detail behu',
      resultTitle: 'Posledne spustenie',
    },
    form: {
      runType: {
        label: 'Typ benchmarku',
        help: 'Vyber endpoint alebo skupinu benchmarkov, ktoru chces spustit.',
        options: {
          all: 'Vsetky benchmarky',
          events_list: 'Zoznam eventov',
          canonical: 'Canonical + publikovanie',
          bot: 'Bot import',
        },
      },
      sampleSize: {
        label: 'Velkost vzorky',
        placeholder: 'Napriklad 200',
        help: 'Pocet meranych poziadaviek. Vyssie cislo = presnejsie porovnanie, ale vacsia zataz.',
      },
      mode: {
        label: 'Rezim',
        help: 'Normalny rezim simuluje bezne spravanie, bez cache testuje horsi scenar.',
        options: {
          normal: 'Normalny',
          no_cache: 'Bez cache',
        },
      },
      botSource: {
        label: 'Zdroj bota',
        help: 'Zdroj feedu pre benchmark bot importu.',
      },
      limit: {
        label: 'Zobrazit poslednych',
      },
      sortBy: {
        label: 'Zoradit podla',
        options: {
          created_at: 'Datum vytvorenia',
          avg_ms: 'Priemerne ms',
          p95_ms: 'P95 ms',
        },
      },
      sortDirection: {
        label: 'Smer',
        asc: 'Vzostupne',
        desc: 'Zostupne',
      },
      dense: {
        label: 'Hustota tabulky',
        help: 'Kompaktne riadky',
      },
      confirmLoad: {
        label: 'Rozumiem, ze benchmark moze docasne zvysit zataz servera.',
        help: 'Pred spustenim potvrdis, ze benchmark bezat moze.',
        actionHint: 'Pred spustenim benchmarku potvrd suhlas so zatazou servera.',
      },
      progress: 'Benchmark prave bezi. Tento krok moze trvat niekolko sekund.',
    },
    actions: {
      runBenchmark: 'Spustit benchmark ({{count}} poziadaviek)',
      running: 'Spustam benchmark...',
      close: 'Zavriet',
      showRawResult: 'Zobrazit surovy vysledok',
    },
    messages: {
      loading: 'Nacitavam metriky...',
      loadFailed: 'Nepodarilo sa nacitat vykonnostne metriky.',
      runSuccess: 'Benchmark bol uspesne dokonceny.',
      runFailed: 'Benchmark sa nepodarilo dokoncit.',
      timeout: 'Poziadavka vyprsala (timeout). Skus to znova s mensou vzorkou.',
      alreadyRunning: 'Benchmark uz momentalne bezi. Skus to o chvilu znova.',
      validationFailed: 'Skontroluj vstupne hodnoty formulara.',
      resultStored: 'Vysledok benchmarku bol ulozeny.',
      resultHint: 'Ak potrebujes detail odpovede API, otvor surovy vystup.',
    },
    validation: {
      sampleRange: 'Velkost vzorky musi byt v rozsahu {{min}} az {{max}}.',
      botSourceRequired: 'Vyber zdroj bota.',
      invalidValue: 'Hodnota nie je platna.',
    },
    table: {
      loading: 'Nacitavam...',
      rowHint: 'Klikni na riadok pre detail behu.',
      columns: {
        key: 'Kluc',
        created: 'Vytvorene',
        avg_ms: 'Priemer ms',
        p95_ms: 'P95 ms',
        db_queries_avg: 'DB dopyty (priemer)',
        trend: 'Trend',
      },
      tooltips: {
        p95_ms: 'P95 je cas, pod ktory sa zmesti 95 % vsetkych merani.',
        db_queries_avg: 'Priemerny pocet SQL dopytov na jednu poziadavku.',
      },
      empty: {
        title: 'Zatial nie su k dispozicii ziadne vysledky benchmarku.',
        description: 'Spusti prvy benchmark a vysledky sa zobrazia v tejto tabulke.',
      },
    },
    trend: {
      noData: 'Bez porovnania',
      stable: 'Bez zmeny oproti predoslemu behu',
      faster: 'Rychlejsie o {{value}} ms oproti predoslemu behu',
      slower: 'Pomalie o {{value}} ms oproti predoslemu behu',
    },
    detail: {
      title: 'Detail behu: {{key}} #{{id}}',
      sections: {
        parameters: 'Parametre behu',
        summary: 'Zakladny suhrn',
        payload: 'Payload',
      },
      fields: {
        runType: 'Typ benchmarku',
        sampleSize: 'Velkost vzorky',
        mode: 'Rezim',
        botSource: 'Zdroj bota',
        createdAt: 'Vytvorene',
        avgMs: 'Priemer',
        p95Ms: 'P95',
        dbQueriesAvg: 'DB dopyty (priemer)',
      },
      values: {
        unknown: 'Nezname',
      },
    },
  },
}

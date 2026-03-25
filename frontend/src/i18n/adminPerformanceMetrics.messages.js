export const performanceMetricsMessages = {
  sk: {
    common: {
      na: '-',
      yes: 'Áno',
      no: 'Nie',
    },
    units: {
      ms: 'ms',
      requests: 'požiadaviek',
    },
    page: {
      title: 'Výkonnostné metriky',
      subtitle: 'Výkonnostné testy servera.',
    },
    cards: {
      runTitle: 'Spustenie benchmarku',
      resultsTitle: 'Najnovšie výsledky',
      resultsMeta: '{{count}} záznamov, posledný beh: {{latest}}',
      resultsMetaEmpty: 'Zatiaľ nie sú uložené žiadne záznamy.',
      detailTitle: 'Detail behu',
    },
    form: {
      runType: {
        label: 'Typ benchmarku',
        options: {
          all: 'Všetky benchmarky',
          events_list: 'Zoznam udalostí',
          canonical: 'Canonical + publikovanie',
          bot: 'Bot import',
        },
      },
      sampleSize: {
        label: 'Vzorka',
        placeholder: '200',
      },
      mode: {
        label: 'Režim',
        options: {
          normal: 'Normálny',
          no_cache: 'Bez cache',
        },
      },
      botSource: {
        label: 'Zdroj bota',
      },
      limit: {
        label: 'Zobraziť posledných',
      },
      sortOrder: {
        label: 'Zoradiť',
        options: {
          created_at_desc: 'Najnovšie',
          created_at_asc: 'Najstaršie',
          avg_ms_asc: 'Najrýchlejšie (avg)',
          avg_ms_desc: 'Najpomalšie (avg)',
          p95_ms_asc: 'Najrýchlejšie (P95)',
          p95_ms_desc: 'Najpomalšie (P95)',
        },
      },
      progress: 'Benchmark práve beží. Môže to trvať niekoľko sekúnd.',
    },
    actions: {
      runBenchmark: 'Spustiť benchmark',
      running: 'Spúšťam benchmark…',
      close: 'Zavrieť',
    },
    messages: {
      loading: 'Načítavam metriky…',
      loadFailed: 'Nepodarilo sa načítať výkonnostné metriky.',
      runSuccess: 'Benchmark bol úspešne dokončený.',
      runFailed: 'Benchmark sa nepodarilo dokončiť.',
      timeout: 'Požiadavka vypršala (timeout). Skús to znova s menšou vzorkou.',
      alreadyRunning: 'Benchmark momentálne beží. Skús to o chvíľu znova.',
      validationFailed: 'Skontroluj vstupné hodnoty formulára.',
    },
    validation: {
      sampleRange: 'Veľkosť vzorky musí byť v rozsahu {{min}} až {{max}}.',
      botSourceRequired: 'Vyber zdroj bota.',
      invalidValue: 'Hodnota nie je platná.',
    },
    table: {
      columns: {
        key: 'Kľúč',
        created: 'Vytvorené',
        avg_ms: 'Priemer ms',
        p95_ms: 'P95 ms',
        db_queries_avg: 'DB dopyty',
        trend: 'Trend',
      },
      empty: {
        title: 'Zatiaľ nie sú k dispozícii žiadne výsledky.',
        description: 'Spusti prvý benchmark a výsledky sa zobrazia v tejto tabuľke.',
      },
    },
    trend: {
      noData: 'Bez porovnania',
      stable: 'Bez zmeny oproti predošlému behu',
      faster: 'Rýchlejšie o {{value}} ms oproti predošlému behu',
      slower: 'Pomalšie o {{value}} ms oproti predošlému behu',
    },
    detail: {
      title: 'Detail behu: {{key}} #{{id}}',
      sections: {
        parameters: 'Parametre behu',
        summary: 'Základný súhrn',
        payload: 'Payload',
      },
      fields: {
        runType: 'Typ benchmarku',
        sampleSize: 'Veľkosť vzorky',
        mode: 'Režim',
        botSource: 'Zdroj bota',
        createdAt: 'Vytvorené',
        avgMs: 'Priemer',
        p95Ms: 'P95',
        dbQueriesAvg: 'DB dopyty (priemer)',
      },
      values: {
        unknown: 'Neznáme',
      },
    },
  },
}

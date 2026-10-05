---
paths:
  - config/data_navigator.php
---

# Config

## Profilo mediatore: stati via *_at, niente accepted_at
proforma.pratiches ha SOLO questi campi milestone: data_inserimento_pratica, sended_at, approved_at, erogated_at, rejected_at (+ created_at/updated_at/upload_at). NON esiste accepted_at: "deliberata"/"delibera" = approved_at. Il testo di stato_pratica è libero/incoerente (DELIBERATA, PERFEZIONATA, INVIO IN ISTRUTTORIA, ...) → mai filtro primario, solo conferma; per classificarlo usare i flag di pratiches_statos (isworking/isrejected/isestingued). Il background del profilo ha un "Vocabolario degli stati" (§1) che mappa le dizioni utente sui predicati *_at e una "§7 Periodi e Range di Date" che lega ogni metrica al suo campo (acquisizione→data_inserimento_pratica, delibere→approved_at, produzione/OAM→erogated_at, fatturato provvigionale→provvigioni.data_fattura, ENASARCO→venasarcotrimestre.competenza+Trimestre). DataNavigatorProfileTest copre questi punti; se cambi le tables del profilo usa assertContains, non assertSame.

## Profilo mediatore: importo, colonne provvigioni utili/da ignorare
Import provvigionale = SEMPRE E SOLO `provvigioni.importo` (ricavo netto incluso). `provvigioni.importo_effettivo` esiste ed è molto diverso (~1/3 del totale) ma NON va usato; nemmeno `importo_erogato`. Colonne provvigioni da IGNORARE (nel prompt §2 "Colonne da ignorare"): importo_effettivo, importo_erogato, received_at, erogated_at, data_status_pratica, montante (sempre 0). Colonne utili aggiunte: `provvigioni.istituto_finanziario` = banca erogante sulla provvigione, sempre valorizzata (~31 valori), equivale a pratiches.denominazione_banca → raggruppa per banca senza JOIN. `provvigioni.status_pratica` e `provvigioni.macrostatus` sono documentate ma ATTUALMENTE tutte NULL: non usarle come filtro. Lookup/codifiche: FK reali su proforma (fk_pratiches_stato_pratica, fk_pratiches_tipo_prodotto, fk_provvigioni_stato). `mediatore.tables` contiene SOLO le entità (`pratiches, provvigioni, venasarcotrimestre`): `pratiches_statos`/`provvigioni_statos` NON vanno lì (finirebbero in "Dati"/schema_legends) — sono catalogate come lookup da `legend:sync` e collegate ai campi via le FK. `mediatore.lookups` = [] (nessun hint necessario). Un `legend:sync` completo pruna le schema_legends non più fra le `tables`.

# Observing Conditions: Bortle

- `bortle_class` is a manual sky-quality preference on a 1-9 scale.
- Lower value means darker sky (`1` best, `9` worst).
- Default value is `6` when user preference is missing (including guests).
- Observing index includes `light_pollution` factor derived from Bortle, so higher Bortle lowers final score.

import unittest
from datetime import datetime

from fastapi.testclient import TestClient

from app import main


class SkySummaryEndpointTest(unittest.TestCase):
    def setUp(self) -> None:
        self.client = TestClient(main.app)

    def test_sky_summary_includes_planets_15_contract_fields(self) -> None:
        response = self.client.get(
            "/sky-summary",
            params={
                "lat": 48.1486,
                "lon": 17.1077,
                "tz": "Europe/Bratislava",
                "date": "2026-02-27",
            },
        )

        self.assertEqual(200, response.status_code)
        payload = response.json()

        sample_at = payload.get("sample_at")
        self.assertIsInstance(sample_at, str)
        self.assertIsNotNone(datetime.fromisoformat(sample_at).tzinfo)
        self.assertIsInstance(payload.get("sun_altitude_deg"), (int, float))

        planets = payload.get("planets")
        self.assertIsInstance(planets, list)
        self.assertGreaterEqual(len(planets), 1)

        for planet in planets:
            self.assertIn("elongation_deg", planet)
            self.assertIsInstance(planet["elongation_deg"], (int, float))
            self.assertGreaterEqual(planet["elongation_deg"], 0.0)
            self.assertLessEqual(planet["elongation_deg"], 180.0)


if __name__ == "__main__":
    unittest.main()

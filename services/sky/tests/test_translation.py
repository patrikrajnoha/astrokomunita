import unittest

from fastapi.testclient import TestClient

from app import main


class _DummyTranslator:
    def translate(self, text: str) -> str:
        return text


class TranslateEndpointTest(unittest.TestCase):
    def setUp(self) -> None:
        main.INTERNAL_TOKEN = "test-token"
        main.translation_state.update(
            {
                "error": None,
                "installed_languages": ["en", "sk"],
                "has_en": True,
                "has_sk": True,
                "has_en_sk_pair": True,
                "translator": _DummyTranslator(),
            }
        )
        self.client = TestClient(main.app)

    def test_translate_empty_text_returns_empty(self) -> None:
        response = self.client.post(
            "/translate",
            json={"text": "", "from": "en", "to": "sk", "domain": "astronomy"},
            headers={"X-Internal-Token": "test-token"},
        )
        self.assertEqual(200, response.status_code)
        self.assertEqual("", response.json()["translated"])

    def test_translate_applies_astronomy_terminology(self) -> None:
        response = self.client.post(
            "/translate",
            json={
                "text": "The Milky Way and International Space Station are visible.",
                "from": "en",
                "to": "sk",
                "domain": "astronomy",
            },
            headers={"X-Internal-Token": "test-token"},
        )
        self.assertEqual(200, response.status_code)
        translated = response.json()["translated"]
        self.assertIn("Mliečna cesta", translated)
        self.assertIn("Medzinárodná vesmírna stanica", translated)


if __name__ == "__main__":
    unittest.main()

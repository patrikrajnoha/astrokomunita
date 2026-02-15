import pytest

from app import main


class DummyTextClassifier:
    def __call__(self, *_args, **_kwargs):
        return [[
            {'label': 'toxic', 'score': 0.05},
            {'label': 'severe_toxic', 'score': 0.01},
            {'label': 'identity_hate', 'score': 0.00},
            {'label': 'threat', 'score': 0.00},
            {'label': 'insult', 'score': 0.02},
            {'label': 'obscene', 'score': 0.01},
        ]]


@pytest.fixture
def mocked_text_classifier(monkeypatch):
    monkeypatch.setattr(main, 'text_classifier', DummyTextClassifier())
    monkeypatch.setattr(main, 'hate_classifier', None)


def test_moderate_text_returns_ok_for_clean_text(mocked_text_classifier):
    payload = main.TextModerationRequest(text='Today is a good day for stargazing.')

    result = main.moderate_text(payload, None)

    assert result['decision'] == 'ok'
    assert result['labels']['rule_match'] == 'none'
    assert result['toxicity_score'] < main.TEXT_FLAG_THRESHOLD


def test_moderate_text_blocks_text_with_threat_rule(mocked_text_classifier):
    payload = main.TextModerationRequest(text='Zabijem ta, ked ta stretnem.')

    result = main.moderate_text(payload, None)

    assert result['decision'] == 'blocked'
    assert result['labels']['rule_match'].startswith('threat:')


def test_decision_from_score_threshold_boundaries():
    assert main.decision_from_score(0.69, 0.70, 0.90) == 'ok'
    assert main.decision_from_score(0.70, 0.70, 0.90) == 'flagged'
    assert main.decision_from_score(0.89, 0.70, 0.90) == 'flagged'
    assert main.decision_from_score(0.90, 0.70, 0.90) == 'blocked'

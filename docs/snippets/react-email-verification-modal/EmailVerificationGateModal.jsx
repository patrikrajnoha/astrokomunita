import React, { useCallback, useEffect, useRef, useState } from 'react';

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export async function mockResendVerificationEmail() {
  await wait(900);
  return { ok: true, message: 'Overovací e-mail bol znovu odoslaný.' };
}

export async function mockCheckEmailVerification(attempt) {
  await wait(900);
  const verified = attempt >= 2;

  return {
    verified,
    message: verified
      ? 'E-mail je overený. Môžeš pokračovať.'
      : 'E-mail zatiaľ nie je overený. Otvor schránku a potvrď overovací odkaz.',
  };
}

export default function EmailVerificationGateModal({
  open,
  email,
  onVerified,
  onResend = mockResendVerificationEmail,
  onCheckVerified = mockCheckEmailVerification,
}) {
  const modalRef = useRef(null);
  const previousActiveElementRef = useRef(null);

  const [isResending, setIsResending] = useState(false);
  const [isChecking, setIsChecking] = useState(false);
  const [feedback, setFeedback] = useState('');
  const [feedbackTone, setFeedbackTone] = useState('info');
  const [checkAttempt, setCheckAttempt] = useState(0);

  const getFocusableElements = useCallback(() => {
    const root = modalRef.current;
    if (!root) return [];

    const selector = [
      'button:not([disabled])',
      '[href]',
      'input:not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    return Array.from(root.querySelectorAll(selector));
  }, []);

  useEffect(() => {
    if (!open) return undefined;

    previousActiveElementRef.current = document.activeElement;

    const originalOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const focusables = getFocusableElements();
    (focusables[0] || modalRef.current)?.focus();

    const handleKeyDown = (event) => {
      if (!open) return;

      if (event.key === 'Escape') {
        event.preventDefault();
        return;
      }

      if (event.key !== 'Tab') return;

      const nodes = getFocusableElements();
      if (!nodes.length) return;

      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      const active = document.activeElement;

      if (event.shiftKey) {
        if (active === first || !modalRef.current?.contains(active)) {
          event.preventDefault();
          last.focus();
        }
        return;
      }

      if (active === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = originalOverflow;

      if (previousActiveElementRef.current && typeof previousActiveElementRef.current.focus === 'function') {
        previousActiveElementRef.current.focus();
      }
    };
  }, [getFocusableElements, open]);

  const handleResend = async () => {
    if (!open || isResending || isChecking) return;

    setFeedback('');
    setIsResending(true);

    try {
      const result = await onResend();
      setFeedback(result?.message || 'Overovací e-mail bol znovu odoslaný.');
      setFeedbackTone('success');
    } catch (error) {
      setFeedback(error?.message || 'Opätovné odoslanie zlyhalo. Skús to znova.');
      setFeedbackTone('error');
    } finally {
      setIsResending(false);
    }
  };

  const handleCheckVerified = async () => {
    if (!open || isResending || isChecking) return;

    const nextAttempt = checkAttempt + 1;
    setCheckAttempt(nextAttempt);
    setFeedback('');
    setIsChecking(true);

    try {
      const result = await onCheckVerified(nextAttempt);

      if (result?.verified) {
        setFeedback(result.message || 'E-mail je overený. Môžeš pokračovať.');
        setFeedbackTone('success');
        await wait(350);
        onVerified?.();
        return;
      }

      setFeedback(result?.message || 'E-mail zatiaľ nie je overený.');
      setFeedbackTone('error');
    } catch (error) {
      setFeedback(error?.message || 'Kontrola overenia zlyhala.');
      setFeedbackTone('error');
    } finally {
      setIsChecking(false);
    }
  };

  if (!open) return null;

  const feedbackClasses = {
    info: 'border-[#222E3F] bg-[#1c2736] text-[#ABB8C9]',
    success: 'border-[#0F73FF] bg-[#1c2736] text-[#FFFFFF]',
    error: 'border-[#EB2452] bg-[#1c2736] text-[#FFFFFF]',
  };

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4" aria-hidden="false">
      <div className="absolute inset-0 bg-[#151d28]/90 backdrop-blur-[2px]" />

      <section
        ref={modalRef}
        className="relative w-full max-w-md rounded-2xl border border-[#222E3F] bg-[#151d28] p-6 shadow-[0_24px_70px_rgba(2,8,20,0.55)] outline-none"
        role="dialog"
        aria-modal="true"
        aria-labelledby="verify-email-title"
        aria-describedby="verify-email-description"
        tabIndex={-1}
      >
        <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[#1c2736] text-[#0F73FF]" aria-hidden="true">
          <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none">
            <path
              d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-11Z"
              stroke="currentColor"
              strokeWidth="1.8"
            />
            <path
              d="m5.5 7.5 6.5 5 6.5-5"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </div>

        <h2 id="verify-email-title" className="text-2xl font-semibold leading-tight text-[#FFFFFF]">
          Over si e-mail
        </h2>

        <p id="verify-email-description" className="mt-3 text-sm leading-6 text-[#ABB8C9]">
          Poslali sme overovací odkaz na{' '}
          <strong className="font-semibold text-[#0F73FF]">{email || 'tvoju e-mailovú adresu'}</strong>.
          Pred pokračovaním v aplikácii musíš e-mail potvrdiť.
        </p>

        <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <button
            type="button"
            className="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F73FF] px-4 text-sm font-semibold text-[#FFFFFF] transition hover:bg-[#0d67e6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F73FF] focus-visible:ring-offset-2 focus-visible:ring-offset-[#151d28] disabled:cursor-not-allowed disabled:opacity-70"
            onClick={handleResend}
            disabled={isResending || isChecking}
          >
            {isResending ? 'Odosielam...' : 'Znovu odoslať e-mail'}
          </button>

          <button
            type="button"
            className="inline-flex h-11 items-center justify-center rounded-xl bg-[#222E3F] px-4 text-sm font-semibold text-[#ABB8C9] transition hover:bg-[#1c2736] hover:text-[#FFFFFF] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F73FF] focus-visible:ring-offset-2 focus-visible:ring-offset-[#151d28] disabled:cursor-not-allowed disabled:opacity-70"
            onClick={handleCheckVerified}
            disabled={isResending || isChecking}
          >
            {isChecking ? 'Kontrolujem...' : 'Už som overil/a'}
          </button>
        </div>

        {feedback ? (
          <p
            className={`mt-4 rounded-xl border px-3 py-2 text-sm ${feedbackClasses[feedbackTone] || feedbackClasses.info}`}
            role="status"
            aria-live="polite"
          >
            {feedback}
          </p>
        ) : null}

        <p className="mt-3 text-xs text-[#ABB8C9]">
          Demo logika: overenie prejde pri druhom kliknutí na kontrolu.
        </p>
      </section>
    </div>
  );
}

import React, { useState } from 'react';
import EmailVerificationGateModal from './EmailVerificationGateModal';

export default function RegistrationSuccessExample() {
  const [isRegistered, setIsRegistered] = useState(false);
  const [isVerified, setIsVerified] = useState(false);

  const handleRegister = () => {
    setIsRegistered(true);
    setIsVerified(false);
  };

  const handleVerified = () => {
    setIsVerified(true);
  };

  return (
    <main className="min-h-screen bg-[#151d28] p-6 text-[#ABB8C9]">
      <h1 className="text-2xl font-semibold text-[#FFFFFF]">Moja aplikácia</h1>
      <p className="mt-2 max-w-xl text-sm leading-6">
        Po registrácii sa zobrazí overovací modal, ktorý zablokuje interakciu, kým používateľ nepotvrdí e-mail.
      </p>

      <button
        type="button"
        onClick={handleRegister}
        className="mt-5 inline-flex h-11 items-center justify-center rounded-xl bg-[#0F73FF] px-4 text-sm font-semibold text-[#FFFFFF] transition hover:bg-[#0d67e6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F73FF] focus-visible:ring-offset-2 focus-visible:ring-offset-[#151d28]"
      >
        Simulovať dokončenie registrácie
      </button>

      <EmailVerificationGateModal
        open={isRegistered && !isVerified}
        email="uzivatel@example.com"
        onVerified={handleVerified}
      />
    </main>
  );
}

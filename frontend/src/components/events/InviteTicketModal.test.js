import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import InviteTicketModal from '@/components/events/InviteTicketModal.vue'

const createEventInviteMock = vi.hoisted(() => vi.fn())
const toastSuccessMock = vi.hoisted(() => vi.fn())
const toastInfoMock = vi.hoisted(() => vi.fn())
const toastWarnMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/invites', () => ({
  createEventInvite: (...args) => createEventInviteMock(...args),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: toastSuccessMock,
    info: toastInfoMock,
    warn: toastWarnMock,
  }),
}))

function makeWrapper() {
  return mount(InviteTicketModal, {
    props: {
      open: true,
      event: {
        id: 12,
        title: 'Noc planet',
        start_at: '2026-03-14T19:30:00Z',
        short: 'Hvezdaren Bratislava',
      },
    },
  })
}

function makeExactMidnightWrapper() {
  return mount(InviteTicketModal, {
    props: {
      open: true,
      event: {
        id: 99,
        title: 'Polnocny event',
        start_at: '2026-01-05T23:00:00Z',
        time_type: 'start',
        time_precision: 'exact',
        source: { name: 'manual' },
      },
    },
  })
}

describe('InviteTicketModal', () => {
  beforeEach(() => {
    createEventInviteMock.mockReset()
    toastSuccessMock.mockReset()
    toastInfoMock.mockReset()
    toastWarnMock.mockReset()
  })

  it('validates attendee_name as required and max length', async () => {
    const wrapper = makeWrapper()

    await wrapper.find('[data-testid="send-invite-btn"]').trigger('click')
    expect(wrapper.text()).toContain('Meno na vstupenke je povinne.')

    await wrapper.find('[data-testid="attendee-name-input"]').setValue('A'.repeat(81))
    await wrapper.find('[data-testid="send-invite-btn"]').trigger('click')
    expect(wrapper.text()).toContain('Meno na vstupenke moze mat najviac 80 znakov.')
  })

  it('calls create invite endpoint with expected payload', async () => {
    createEventInviteMock.mockResolvedValue({
      data: {
        data: {
          id: 22,
          token: 'public-token-123',
        },
      },
    })

    const wrapper = makeWrapper()

    await wrapper.find('[data-testid="attendee-name-input"]').setValue('Marek')
    await wrapper.find('.optionalToggle').trigger('click')
    await wrapper.find('input[type="email"]').setValue('marek@example.com')
    await wrapper.find('textarea').setValue('Tesim sa na teba')
    await wrapper.find('[data-testid="send-invite-btn"]').trigger('click')

    expect(createEventInviteMock).toHaveBeenCalledWith(12, {
      attendee_name: 'Marek',
      message: 'Tesim sa na teba',
      invitee_email: 'marek@example.com',
    })
    expect(toastSuccessMock).toHaveBeenCalled()
  })

  it('uses navigator.share when share button is clicked', async () => {
    const shareMock = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(globalThis.navigator, 'share', {
      configurable: true,
      value: shareMock,
    })

    const wrapper = makeWrapper()
    await wrapper.find('[data-testid="attendee-name-input"]').setValue('Marek')
    await wrapper.find('.menuBtn').trigger('click')
    await wrapper.find('[data-testid="share-ticket-btn"]').trigger('click')

    expect(shareMock).toHaveBeenCalledTimes(1)
  })

  it('calls window.print when print button is clicked', async () => {
    const printMock = vi.fn()
    Object.defineProperty(window, 'print', {
      configurable: true,
      value: printMock,
    })

    const wrapper = makeWrapper()
    await wrapper.find('.menuBtn').trigger('click')
    await wrapper.find('[data-testid="print-ticket-btn"]').trigger('click')

    expect(printMock).toHaveBeenCalledTimes(1)
  })

  it('renders explicit local midnight with SK label', () => {
    const wrapper = makeExactMidnightWrapper()

    expect(wrapper.text()).toContain('6. januára 2026')
    expect(wrapper.text()).toContain('00:00 (SK)')
  })
})

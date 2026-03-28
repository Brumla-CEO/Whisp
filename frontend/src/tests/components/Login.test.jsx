

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AuthContext } from '../../Context/AuthContext'
import Login from '../../Components/Login'

// ─── Mock AuthContext ────────────────────────────────────────────────────────

const createMockContext = (overrides = {}) => ({
    login: vi.fn(),
    ...overrides,
})

// Helper pro renderování s fake contextem
const renderLogin = (contextOverrides = {}) => {
    const mockContext = createMockContext(contextOverrides)
    return {
        ...render(
            <AuthContext.Provider value={mockContext}>
                <Login />
            </AuthContext.Provider>
        ),
        mockContext,
    }
}
// ─────────────────────────────────────────────────────────────────────────────

describe('Login komponenta', () => {

    it('zobrazí formulář s email a password polem', () => {
        renderLogin()

        expect(screen.getByPlaceholderText('Email')).toBeInTheDocument()
        expect(screen.getByPlaceholderText('Heslo')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /přihlásit/i })).toBeInTheDocument()
    })

    it('zavolá login() s trimovaným emailem a heslem po submit', async () => {
        const user = userEvent.setup()
        const { mockContext } = renderLogin()

        await user.type(screen.getByPlaceholderText('Email'), '  test@example.com  ')
        await user.type(screen.getByPlaceholderText('Heslo'), 'heslo123')
        await user.click(screen.getByRole('button', { name: /přihlásit/i }))

        expect(mockContext.login).toHaveBeenCalledOnce()
        expect(mockContext.login).toHaveBeenCalledWith('test@example.com', 'heslo123')
    })

    it('zobrazí chybovou zprávu při neúspěšném přihlášení', async () => {
        const user = userEvent.setup()

        const failingLogin = vi.fn().mockRejectedValue({
            response: { data: { message: 'Neplatný email nebo heslo' } },
        })

        renderLogin({ login: failingLogin })

        await user.type(screen.getByPlaceholderText('Email'), 'test@example.com')
        await user.type(screen.getByPlaceholderText('Heslo'), 'spatne-heslo')
        await user.click(screen.getByRole('button', { name: /přihlásit/i }))

        await waitFor(() => {
            expect(screen.getByText('Neplatný email nebo heslo')).toBeInTheDocument()
        })
    })

    it('zobrazí fallback chybu při síťové chybě', async () => {
        const user = userEvent.setup()

        const networkError = vi.fn().mockRejectedValue(new Error('Network Error'))
        renderLogin({ login: networkError })

        await user.type(screen.getByPlaceholderText('Email'), 'test@example.com')
        await user.type(screen.getByPlaceholderText('Heslo'), 'heslo123')
        await user.click(screen.getByRole('button', { name: /přihlásit/i }))

        await waitFor(() => {
            expect(
                screen.getByText('Neplatný email nebo heslo')
            ).toBeInTheDocument()
        })
    })


    it('nezavolá login() když email je prázdný (HTML required)', async () => {
        const user = userEvent.setup()
        const { mockContext } = renderLogin()

        await user.type(screen.getByPlaceholderText('Heslo'), 'heslo123')
        await user.click(screen.getByRole('button', { name: /přihlásit/i }))

        expect(mockContext.login).not.toHaveBeenCalled()
    })
})

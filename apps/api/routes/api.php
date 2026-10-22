<?php

use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ClaimController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\FinanceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\ReadyController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\Platform\PlatformTenantController;
use App\Http\Controllers\Api\V1\PolicyController;
use App\Http\Controllers\Api\V1\Portal\CustomerPortalController;
use App\Http\Controllers\Api\V1\Portal\PortalAuthController;
use App\Http\Controllers\Api\V1\PremiumInstructionController;
use App\Http\Controllers\Api\V1\QuoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
    Route::get('/ready', ReadyController::class);

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('/portal/auth/register', [PortalAuthController::class, 'register'])
        ->middleware('throttle:5,1');
    Route::post('/portal/auth/forgot-password', [PortalAuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1');
    Route::post('/portal/auth/reset-password', [PortalAuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/onboarding/organization', [OnboardingController::class, 'update']);

        Route::get('/catalog/appointments', [CatalogController::class, 'carriers']);
        Route::get('/catalog/products', [CatalogController::class, 'products']);
        Route::get('/catalog/carriers', [CatalogController::class, 'directory']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);

        Route::get('/leads', [LeadController::class, 'index']);
        Route::post('/leads', [LeadController::class, 'store']);
        Route::patch('/leads/{id}/stage', [LeadController::class, 'updateStage']);

        Route::get('/quotes', [QuoteController::class, 'index']);
        Route::post('/quotes', [QuoteController::class, 'store']);
        Route::get('/quotes/{id}', [QuoteController::class, 'show']);
        Route::post('/quotes/{id}/validate', [QuoteController::class, 'validateDraft']);
        Route::post('/quotes/{id}/submit', [QuoteController::class, 'submitToInsurer']);
        Route::post('/quotes/{id}/insurer-confirmation', [QuoteController::class, 'recordInsurerConfirmation']);

        Route::get('/applications', [ApplicationController::class, 'index']);
        Route::post('/applications', [ApplicationController::class, 'store']);
        Route::post('/applications/{id}/submit', [ApplicationController::class, 'submit']);

        Route::get('/policies', [PolicyController::class, 'index']);
        Route::get('/policies/renewals/upcoming', [PolicyController::class, 'upcomingRenewals']);
        Route::post('/policies/intake', [PolicyController::class, 'intake']);
        Route::get('/policies/{id}', [PolicyController::class, 'show']);
        Route::post('/policies/{id}/endorsements', [PolicyController::class, 'requestEndorsement']);
        Route::post('/policies/{id}/cancellations', [PolicyController::class, 'requestCancellation']);
        Route::post('/policies/{id}/renewals', [PolicyController::class, 'openRenewal']);
        Route::post('/policies/{id}/documents', [PolicyController::class, 'registerDocument']);
        Route::post('/endorsements/{endorsementId}/apply', [PolicyController::class, 'applyEndorsement']);
        Route::post('/cancellations/{cancellationId}/confirm', [PolicyController::class, 'confirmCancellation']);

        Route::get('/premium-instructions', [PremiumInstructionController::class, 'index']);
        Route::post('/premium-instructions', [PremiumInstructionController::class, 'store']);

        Route::get('/claims', [ClaimController::class, 'index']);
        Route::post('/claims', [ClaimController::class, 'store']);
        Route::get('/claims/{id}', [ClaimController::class, 'show']);
        Route::post('/claims/{id}/submit', [ClaimController::class, 'submit']);
        Route::post('/claims/{id}/acknowledge', [ClaimController::class, 'acknowledge']);
        Route::post('/claims/{id}/insurer-update', [ClaimController::class, 'insurerUpdate']);

        Route::get('/finance/overview', [FinanceController::class, 'overview']);
        Route::get('/finance/commissions', [FinanceController::class, 'commissions']);
        Route::get('/finance/journals', [FinanceController::class, 'journals']);
        Route::post('/finance/commission-schedules', [FinanceController::class, 'storeSchedule']);
        Route::post('/finance/commissions/expect', [FinanceController::class, 'expectCommission']);
        Route::post('/finance/commissions/{id}/confirm', [FinanceController::class, 'confirmCommission']);
        Route::post('/finance/commissions/{id}/receive', [FinanceController::class, 'receiveCommission']);
        Route::post('/finance/reconciliations', [FinanceController::class, 'createReconciliation']);

        Route::get('/integrations/catalog', [IntegrationController::class, 'catalog']);
        Route::get('/integrations', [IntegrationController::class, 'index']);
        Route::post('/integrations/{id}/sandbox', [IntegrationController::class, 'enableSandbox']);
        Route::post('/integrations/{id}/sandbox-probe', [IntegrationController::class, 'sandboxProbe']);
        Route::put('/settings/branding', [IntegrationController::class, 'updateBranding']);

        // Customer self-service portal (own records only) — CUS-* journeys
        Route::prefix('portal')->group(function () {
            Route::get('/me', [CustomerPortalController::class, 'me']);
            Route::put('/profile', [CustomerPortalController::class, 'updateProfile']);
            Route::post('/auth/otp/request', [PortalAuthController::class, 'requestOtp']);
            Route::post('/auth/otp/verify', [PortalAuthController::class, 'verifyOtp']);

            Route::get('/products', [CustomerPortalController::class, 'products']);
            Route::get('/policies', [CustomerPortalController::class, 'policies']);
            Route::get('/policies/{id}', [CustomerPortalController::class, 'showPolicy']);
            Route::get('/documents', [CustomerPortalController::class, 'documents']);
            Route::get('/documents/{id}/download', [CustomerPortalController::class, 'downloadDocument']);

            Route::get('/premiums', [CustomerPortalController::class, 'premiumDues']);
            Route::get('/payments', [CustomerPortalController::class, 'payments']);
            Route::post('/payments', [CustomerPortalController::class, 'initiatePayment']);
            Route::post('/payments/{id}/sandbox-confirm', [CustomerPortalController::class, 'confirmPaymentSandbox']);
            Route::get('/payments/{id}/receipt', [CustomerPortalController::class, 'paymentReceipt']);

            Route::get('/claims', [CustomerPortalController::class, 'claims']);
            Route::post('/claims', [CustomerPortalController::class, 'storeClaim']);
            Route::get('/claims/{id}', [CustomerPortalController::class, 'showClaim']);
            Route::post('/claims/{id}/submit', [CustomerPortalController::class, 'submitClaim']);
            Route::post('/claims/{id}/evidence', [CustomerPortalController::class, 'attachClaimEvidence']);

            Route::get('/renewals', [CustomerPortalController::class, 'renewals']);
            Route::get('/service-requests', [CustomerPortalController::class, 'serviceRequests']);
            Route::post('/service-requests', [CustomerPortalController::class, 'storeServiceRequest']);
            Route::get('/notifications', [CustomerPortalController::class, 'notifications']);
            Route::post('/notifications/{id}/read', [CustomerPortalController::class, 'markNotificationRead']);
            Route::get('/communications', [CustomerPortalController::class, 'communicationHistory']);
        });
    });

    // SaaS control plane — separate route tree; requires APP_EDITION=saas
    Route::prefix('platform')->middleware('saas')->group(function () {
        Route::post('/auth/login', [PlatformTenantController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/tenants', [PlatformTenantController::class, 'index']);
            Route::post('/tenants', [PlatformTenantController::class, 'store']);
            Route::post('/tenants/{id}/suspend', [PlatformTenantController::class, 'suspend']);
            Route::post('/tenants/{id}/invoices', [PlatformTenantController::class, 'issueInvoice']);
            Route::get('/plans', [PlatformTenantController::class, 'plans']);
            Route::get('/invoices', [PlatformTenantController::class, 'invoices']);
        });
    });
});

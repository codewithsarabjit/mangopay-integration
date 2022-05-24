<?php
class MangoPay {
    protected $api;

    public function __construct()
    {
        // initialize mangopay api
        $this->api = new \MangoPay\MangoPayApi();

        // configuration
        $this->api->Config->ClientId = 'mangopay.client_id';
        $this->api->Config->ClientPassword = 'mangopay.client_password';
        $this->api->Config->TemporaryFolder = 'app/mangopay-temp';
        $this->api->Config->BaseUrl = 'mangopay.base_url';
    }

    public function createNaturalUser($user)
    {
        try {
            $naturalUser = new \MangoPay\UserNatural();
            $naturalUser->FirstName = $user->firstname;
            $naturalUser->LastName = $user->lastname;

            $naturalUser->Address = new \MangoPay\Address();
            $naturalUser->Address->AddressLine1 = $user->address;
            $naturalUser->Address->City = $user->place;
            $naturalUser->Address->PostalCode = $user->zip;
            $naturalUser->Address->Country = "DE";

            $naturalUser->Birthday = !is_null($user->dob) ? strtotime($user->dob) : 1;
            $naturalUser->Nationality = $user->nationality;
            $naturalUser->CountryOfResidence = "DE";
            $naturalUser->Email = $user->email;
            $naturalUser->Capacity = 'NORMAL';

            return $this->api->Users->Create($naturalUser);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createLegalUser($user)
    {
        try {
            $legalUser = $this->getLegalUser($user);

            return $this->api->Users->Create($legalUser);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function updateLegalUser($user)
    {
        try {
            $legalUser = $this->getLegalUser($user);

            return $this->api->Users->Update($legalUser);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getLegalUser($user)
    {
        $legalUser = new \MangoPay\UserLegal();

        if(!is_null($user->payment_provider_user_id)) {
            $legalUser->Id = $user->payment_provider_user_id;
        }

        if($user->company->business_type === 'soletrader') {
            $legalUser->LegalPersonType = 'SOLETRADER';
        } else {
            $legalUser->LegalPersonType = 'BUSINESS';
        }

        $legalUser->HeadquartersAddress = new \MangoPay\Address();
        $legalUser->HeadquartersAddress->AddressLine1 = $user->company->address;
        $legalUser->HeadquartersAddress->City = $user->company->place;
        $legalUser->HeadquartersAddress->PostalCode = $user->company->zip;
        $legalUser->HeadquartersAddress->Country = "DE";

        if($user->company->business_type === 'soletrader') {
            $legalUser->Name = $user->firstname . " " . $user->lastname;
        } else {
            $legalUser->Name = $user->company->name;
        }
        

        $legalUser->LegalRepresentativeAddress = new \MangoPay\Address();
        $legalUser->LegalRepresentativeAddress->AddressLine1 = $user->address;
        $legalUser->LegalRepresentativeAddress->City = $user->place;
        $legalUser->LegalRepresentativeAddress->PostalCode = $user->zip;
        $legalUser->LegalRepresentativeAddress->Country = "DE";
        $legalUser->LegalRepresentativeBirthday = !is_null($user->dob) ? strtotime($user->dob) : 1;
        $legalUser->LegalRepresentativeCountryOfResidence = "DE";
        $legalUser->LegalRepresentativeNationality = $user->nationality;;
        $legalUser->LegalRepresentativeEmail = $user->email;
        $legalUser->LegalRepresentativeFirstName = $user->firstname;
        $legalUser->LegalRepresentativeLastName = $user->lastname;
        $legalUser->Email = $user->email;
        $legalUser->CompanyNumber = $user->company->registry_court_nr;

        return $legalUser;
    }

    public function getUser($userId)
    {
        try {
            return $this->api->Users->Get($userId);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function registerCard($user, $cardNumber, $cardExpirationDate, $cardCvx)
    {
        // create card registration
        try {
            $CardRegistration = new \MangoPay\CardRegistration();
            $CardRegistration->UserId = $user->Id;
            $CardRegistration->Currency = "EUR";
            $CardRegistration->CardType = "CB_VISA_MASTERCARD";

            $card = $this->api->CardRegistrations->Create($CardRegistration);

        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getCard($cardId)
    {
        try {
            return $this->api->Cards->Get($cardId);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createTransaction($cardId, $creditedWalletId, $ipAddress, $browserInfo, $amount, $tag='', $secureMode=true)
    {
        try {
            $card = $this->api->Cards->Get($cardId);

            $payIn = new \MangoPay\PayIn();

            $payIn->AuthorId = $card->UserId;

            $payIn->CreditedWalletId = $creditedWalletId;

            $payIn->DebitedFunds = new \MangoPay\Money();
            $payIn->DebitedFunds->Amount = $amount * 100; // because amount should be in the smallest sub-division of the currency, e.g. 12.60 EUR would be represented as 1260
            $payIn->DebitedFunds->Currency = 'EUR';

            $payIn->Fees = new \MangoPay\Money();
            $payIn->Fees->Amount = 0;
            $payIn->Fees->Currency = 'EUR';

            $payIn->PaymentDetails = new \MangoPay\PayInPaymentDetailsCard();
            $payIn->PaymentDetails->CardType = $card->CardType;
            $payIn->PaymentDetails->CardId = $card->Id;

            $payIn->ExecutionDetails = new \MangoPay\PayInExecutionDetailsDirect();
            if($secureMode) {
                $payIn->ExecutionDetails->SecureMode = 'FORCE';
                $payIn->ExecutionDetails->SecureModeReturnURL = route('payment_notification');
            } else {
                $payIn->ExecutionDetails->SecureMode = 'DEFAULT';
                $payIn->ExecutionDetails->SecureModeReturnURL = route('payment_notification');
            }

            $payIn->IpAddress = $ipAddress;
            $payIn->BrowserInfo = $browserInfo;

            $payIn->Tag = $tag;

            return $this->api->PayIns->Create($payIn);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getTransaction($transactionId)
    {
        try {
            return $this->api->PayIns->Get($transactionId);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getPayoutTransaction($transactionId)
    {
        try {
            return $this->api->PayOuts->Get($transactionId);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getBankAccount($userId, $bankAccountId)
    {
        try {
            return $this->api->Users->GetBankAccount($userId, $bankAccountId);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getMandate($madateId)
    {
        try {
            return $this->api->Mandates->Get($madateId);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createBankAccount($user, $mangopayUserId, $iban)
    {
        try {
            $bankAccount = new \MangoPay\BankAccount();

            $bankAccount->OwnerName = $user->firstname . ' ' . $user->lastname;

            $bankAccount->OwnerAddress = new \MangoPay\Address();

            $bankAccount->OwnerAddress->AddressLine1 = $user->address;
            $bankAccount->OwnerAddress->City = $user->place;
            $bankAccount->OwnerAddress->PostalCode = $user->zip;
            $bankAccount->OwnerAddress->Country = "DE";

            $bankAccount->Details = new \MangoPay\BankAccountDetailsIBAN();
            $bankAccount->Details->IBAN = $iban;

            $bankAccount->UserId = $mangopayUserId;

            return $this->api->Users->CreateBankAccount($mangopayUserId, $bankAccount);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createMandate($bankAccountId)
    {
        try {
            $mandate = new \MangoPay\Mandate();
            $mandate->BankAccountId = $bankAccountId;
            $mandate->ReturnURL = route('mandate_notification');
            $mandate->Culture = 'DE';

            return $this->api->Mandates->Create($mandate);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createDirectDebitTransaction($amount, $mangopayUserId, $mangopayMandateId, $creditedWalletId, $tag = '', $secureMode=true)
    {
        try {
            $payIn = new \MangoPay\PayIn();

            $payIn->AuthorId = $mangopayUserId;

            $payIn->CreditedWalletId = $creditedWalletId;

            $payIn->DebitedFunds = new \MangoPay\Money();
            $payIn->DebitedFunds->Amount = $amount * 100; // because amount should be in the smallest sub-division of the currency, e.g. 12.60 EUR would be represented as 1260
            $payIn->DebitedFunds->Currency = 'EUR';

            $payIn->Fees = new \MangoPay\Money();
            $payIn->Fees->Amount = 0;
            $payIn->Fees->Currency = 'EUR';

            $payIn->PaymentDetails = new \MangoPay\PayInPaymentDetailsDirectDebit();
            $payIn->PaymentDetails->MandateId = $mangopayMandateId;

            $payIn->ExecutionDetails = new \MangoPay\PayInExecutionDetailsDirect();
            if($secureMode) {
                $payIn->ExecutionDetails->SecureMode = 'FORCE';
                $payIn->ExecutionDetails->SecureModeReturnURL = route('payment_notification_direct_debit');
            } else {
                $payIn->ExecutionDetails->SecureMode = 'DEFAULT';
                $payIn->ExecutionDetails->SecureModeReturnURL = route('payment_notification_direct_debit');
            }

            $payIn->Tag = $tag;

            return $this->api->PayIns->Create($payIn);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getWallet($walletId)
    {
        try {
            return $this->api->Wallets->Get($walletId);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createWallet($ownerId, $description)
    {
        try {
            $Wallet = new \MangoPay\Wallet();
            $Wallet->Owners = array ($ownerId);
            $Wallet->Description = $description;
            $Wallet->Currency = "EUR";
            return $this->api->Wallets->Create($Wallet);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getIbanBankingAlias($bankingAliasId)
    {
        try {
            return $this->api->BankingAliases->Get($bankingAliasId);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createIbanBankingAlias($creditedUserId, $walletId, $ownername)
    {
        try {
            $bankingAliasIban = new \MangoPay\BankingAliasIBAN();
            $bankingAliasIban->CreditedUserId = $creditedUserId;
            $bankingAliasIban->WalletId = $walletId;
            $bankingAliasIban->Country = 'LU';
            $bankingAliasIban->OwnerName = $ownername;
            $bankingAliasIban->Active = true;
            return $this->api->BankingAliases->Create($bankingAliasIban);
        } catch(\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createTransferTransaction($amount, $fee, $mangopayUserId, $debitedWalletId, $creditedWalletId=null, $tag='')
    {
        try {
            $transfer = new \MangoPay\Transfer();

            $transfer->AuthorId = $mangopayUserId;

            $transfer->CreditedWalletId = $creditedWalletId ?? config('mangopay.credit_wallet_id');
            $transfer->DebitedWalletId = $debitedWalletId;

            $transfer->DebitedFunds = new \MangoPay\Money();
            $transfer->DebitedFunds->Amount = $amount * 100; // because amount should be in the smallest sub-division of the currency, e.g. 12.60 EUR would be represented as 1260
            $transfer->DebitedFunds->Currency = 'EUR';

            $transfer->Fees = new \MangoPay\Money();
            $transfer->Fees->Amount = $fee * 100;
            $transfer->Fees->Currency = 'EUR';

            $transfer->Tag = $tag;

            return $this->api->Transfers->Create($transfer);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createPayoutTransaction($amount, $fee, $mangopayUserId, $debitedWalletId, $bankAccountId, $tag='')
    {
        try {
            $payout = new \MangoPay\PayOut();

            $payout->AuthorId = $mangopayUserId;

            $payout->DebitedWalletId = $debitedWalletId;

            $payout->BankAccountId = $bankAccountId;

            $payout->MeanOfPaymentDetails = new \MangoPay\PayOutPaymentDetailsBankWire();

            $payout->DebitedFunds = new \MangoPay\Money();
            $payout->DebitedFunds->Amount = $amount * 100; // because amount should be in the smallest sub-division of the currency, e.g. 12.60 EUR would be represented as 1260
            $payout->DebitedFunds->Currency = 'EUR';

            $payout->Fees = new \MangoPay\Money();
            $payout->Fees->Amount = $fee * 100;
            $payout->Fees->Currency = 'EUR';

            $payout->Tag = $tag;

            return $this->api->PayOuts->Create($payout);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getKycDocument($mangopayUserId, $kycDocumentId)
    {
        try {
            return $this->api->Users->GetKycDocument($mangopayUserId, $kycDocumentId);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createKycDocument($mangopayUserId, $documentType)
    {
        try {
            $kycDocument = new \MangoPay\KycDocument();
            $kycDocument->Type = $documentType;

            return $this->api->Users->CreateKycDocument($mangopayUserId, $kycDocument);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createKycPage($mangopayUserId, $kycDocumentId, $file)
    {
        try {
            return $this->api->Users->CreateKycPageFromFile($mangopayUserId, $kycDocumentId, $file);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function validateKycDocument($mangopayUserId, $kycDocumentId)
    {
        try {
            $KycDocument = new \MangoPay\KycDocument();
            $KycDocument->Id = $kycDocumentId;
            $KycDocument->Status = \MangoPay\KycDocumentStatus::ValidationAsked; // VALIDATION_ASKED
            return $this->api->Users->UpdateKycDocument($mangopayUserId, $KycDocument);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createUboDeclaration($mangopayUserId)
    {
        try {
            return $this->api->UboDeclarations->Create($mangopayUserId);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function getUboDeclaration($mangopayUserId, $uboDeclarationId)
    {
        try {
            return $this->api->UboDeclarations->Get($mangopayUserId, $uboDeclarationId);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function createUbo($user, $mangopayUserId, $uboDeclarationId)
    {
        try {
            $ubo = new \MangoPay\Ubo();
            $ubo->FirstName = $user['firstname'];
            $ubo->LastName = $user['lastname'];
            $ubo->Address = new \MangoPay\Address();
            $ubo->Address->AddressLine1 = $user['address']['address'];
            $ubo->Address->City = $user['address']['city'];
            $ubo->Address->PostalCode = $user['address']['zip'];;
            $ubo->Address->Country = 'DE';
            $ubo->Nationality = 'DE';
            $ubo->Birthday = $user['dob'];
            $ubo->Birthplace = new \MangoPay\Birthplace();
            $ubo->Birthplace->City = $user['birth_city'];
            $ubo->Birthplace->Country = $user['birth_country'];

            return $this->api->UboDeclarations->CreateUbo($mangopayUserId, $uboDeclarationId, $ubo);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function updateUbo($user, $mangopayUserId, $uboDeclarationId, $uboId)
    {
        try {
            $ubo = new \MangoPay\Ubo();
            $ubo->Id = $uboId;
            $ubo->FirstName = $user['firstname'];
            $ubo->LastName = $user['lastname'];
            $ubo->Address = new \MangoPay\Address();
            $ubo->Address->AddressLine1 = $user['address']['address'];
            $ubo->Address->City = $user['address']['city'];
            $ubo->Address->PostalCode = $user['address']['zip'];;
            $ubo->Address->Country = 'DE';
            $ubo->Nationality = 'DE';
            $ubo->Birthday = $user['dob'];
            $ubo->Birthplace = new \MangoPay\Birthplace();
            $ubo->Birthplace->City = $user['birth_city'];
            $ubo->Birthplace->Country = $user['birth_country'];

            return $this->api->UboDeclarations->UpdateUbo($mangopayUserId, $uboDeclarationId, $ubo);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }

    public function submitUboDeclaration($mangopayUserId, $uboDeclarationId)
    {
        try {
            return $this->api->UboDeclarations->SubmitForValidation($mangopayUserId, $uboDeclarationId);
        } catch (\MangoPay\Libraries\Exception $ex) {
            return null;
        }
    }
}

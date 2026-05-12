<?php
/**
 * PDF AcroForm field mapping definitions.
 *
 * Each entry maps a database column (from wp_az_case_questionnaire) or a
 * computed key to the exact AcroForm field name(s) inside a PDF template.
 *
 * Values may be a STRING (single field) or an ARRAY (same data fills multiple
 * fields in the same PDF — e.g., the petitioner name appears in the header
 * block, the caption block, and the body section of most Arizona court forms).
 *
 * The resolve() method in Case_Engine_PDF_Mapper handles both forms.
 *
 * Field names were extracted directly from each PDF via pypdf AcroForm
 * inspection — they are the internal widget names, not the visible labels.
 *
 * Signature / notary fields are intentionally OMITTED — left blank for signing.
 *
 * @package Case_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(

    // =========================================================================
    // WOC — STEP 1 — Petition for Dissolution (drda10fz.pdf)
    // =========================================================================
    'woc_petition' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of PetitionerParty A', 'Name DRDA10f' ],
        'petitioner_address'        => [ 'Address if not protected', 'Address DRDA10f' ],
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => [ 'Name of RespondentParty B', 'Name_2 DRDA10f' ],
        'respondent_address'        => 'Address_2 DRDA10f',
        'marriage_city_state'       => 'City and state or country where we were married DRDA10f',
        'married_name_first'        => 'Married name first DRDA10f',
        'married_name_middle'       => 'Married name middle DRDA10f',
        'married_name_last'         => 'Married name last DRDA10f',
        'restore_name_first'        => 'Restore name first DRDA10f',
        'restore_name_middle'       => 'Restore name middle DRDA10f',
        'restore_name_last'         => 'Restore name last DRDA10f',
        // Community debts (up to 5)
        'debt_description_1'        => 'Description of Debt 1 DRDA10f',
        'debt_responsible_a_1'      => 'A debt 1 DRDA10f',
        'debt_responsible_b_1'      => 'B debt 1 DRDA10f',
        'debt_description_2'        => 'Description of Debt 2 DRDA10f',
        'debt_responsible_a_2'      => 'A debt 2 DRDA10f',
        'debt_responsible_b_2'      => 'B debt 2 DRDA10f',
        'debt_description_3'        => 'Description of Debt 3 DRDA10f',
        'debt_responsible_a_3'      => 'A debt 3 DRDA10f',
        'debt_responsible_b_3'      => 'B debt 3 DRDA10f',
        'debt_description_4'        => 'Description of Debt 4 DRDA10f',
        'debt_responsible_a_4'      => 'A debt 4 DRDA10f',
        'debt_responsible_b_4'      => 'B debt 4 DRDA10f',
        'debt_description_5'        => 'Description of Debt 5 DRDA10f',
        'debt_responsible_a_5'      => 'A debt 5 DRDA10f',
        'debt_responsible_b_5'      => 'B debt 5 DRDA10f',
    ),

    // =========================================================================
    // WOC — STEP 1 — Summons (dr11fz.pdf)
    // =========================================================================
    'woc_summons' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Nameof Petitioner  Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case No',
        'respondent_full_name'      => [ 'Name of Respondent  Party B', 'Name of opposing party DR11f' ],
    ),

    // =========================================================================
    // WOC — STEP 1 — Sensitive Data Cover Sheet (drsds10f-annz.pdf)
    // =========================================================================
    'woc_sensitive_data' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Petitioner  Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Respondent  Party B',
    ),

    // =========================================================================
    // SHARED — Notice Regarding Creditors (dr16fz.pdf) — WC and WOC same form
    // =========================================================================
    'notice_regarding_creditors' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of PetitionerParty A', 'Your Name DR16f' ],
        'petitioner_address'        => [ 'Address if not protected', 'Your Address 1 DR16f' ],
        'petitioner_city_state_zip' => [ 'City State Zip Code', 'Your Address 2 DR16f' ],
        'petitioner_phone'          => [ 'Telephone', 'Your Phone Number DR16f' ],
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => [ 'Name of RespondentParty B', 'Your Spouses Name DR16f' ],
        'respondent_address'        => 'Your Spouses Address DR16f',
    ),

    // =========================================================================
    // SHARED — Preliminary Injunction (dr14fz.pdf) — WC and WOC same form
    // =========================================================================
    'woc_preliminary_injunction' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of PetitionerParty A', 'Name DR14f' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => [ 'Name of RespondentParty B', 'Name_2 DR14f' ],
    ),

    // =========================================================================
    // SHARED — Acceptance of Service (dr22fz.pdf)
    // =========================================================================
    'woc_acceptance_of_service' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of Petitioner Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of Respondent Party B',
    ),

    // =========================================================================
    // SHARED — Affidavit of Service with Signature Confirmation (dr24fz.pdf)
    // =========================================================================
    'woc_affidavit_service_signature' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of Petitioner Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of Respondent Party B',
    ),

    // =========================================================================
    // SHARED — Affidavit of Service by Alternative Means (dr31fz.pdf)
    // =========================================================================
    'woc_affidavit_service_alt' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of Petitioner Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of Respondent Party B',
    ),

    // =========================================================================
    // SHARED — Application and Affidavit for Entry of Default (drd61fz.pdf)
    // =========================================================================
    'woc_default_application' => array(
        // Note: two spaces before "Party A/B" in this form's field names
        'petitioner_full_name'      => [ 'Person Filing', 'Name of Petitioner  Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of Respondent  Party B',
        'respondent_address'        => 'the last known mailing address for the Party in default is DRD61f',
        'date_of_service'           => 'Date of Service DRD61f',
    ),

    // =========================================================================
    // SHARED — Default Information for Spousal Maintenance (drd62fz.pdf)
    // =========================================================================
    'woc_default_spousal_maintenance' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of Petitioner  Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of Respondent  Party B',
    ),

    // =========================================================================
    // WOC — STEP 4 — Divorce Decree (drda81fz.pdf)
    // =========================================================================
    'woc_divorce_decree' => array(
        // No Email Address field in this form's header
        'petitioner_full_name'      => [ 'Person Filing', 'Name of Petitioner Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of Respondent Party B',
        // Community debts (section 5, up to 6)
        'debt_description_1'        => 'Creditors 1 DRDA81f',
        'debt_responsible_a_1'      => 'Party A 1 DRDA81f',
        'debt_responsible_b_1'      => 'Party B 1 DRDA81f',
        'debt_description_2'        => 'Creditors 2 DRDA81f',
        'debt_responsible_a_2'      => 'Party A 2 DRDA81f',
        'debt_responsible_b_2'      => 'Party B 2 DRDA81f',
        'debt_description_3'        => 'Creditors 3 DRDA81f',
        'debt_responsible_a_3'      => 'Party A 3 DRDA81f',
        'debt_responsible_b_3'      => 'Party B 3 DRDA81f',
        'debt_description_4'        => 'Creditors 4 DRDA81f',
        'debt_responsible_a_4'      => 'Party A 4 DRDA81f',
        'debt_responsible_b_4'      => 'Party B 4 DRDA81f',
        'debt_description_5'        => 'Creditors 5 DRDA81f',
        'debt_responsible_a_5'      => 'Party A 5 DRDA81f',
        'debt_responsible_b_5'      => 'Party B 5 DRDA81f',
        'debt_description_6'        => 'Creditors 6 DRDA81f',
        'debt_responsible_a_6'      => 'Party A 6 DRDA81f',
        'debt_responsible_b_6'      => 'Party B 6 DRDA81f',
    ),

    // =========================================================================
    // SHARED — Motion and Affidavit for Default Decree without Hearing (drd68fz.pdf)
    // =========================================================================
    'woc_motion_default_decree' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of Petitioner Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of Respondent Party B',
    ),

    // =========================================================================
    // WOC — STEP 4b — Consent Decree (dra71fz.pdf)
    // =========================================================================
    'woc_consent_decree' => array(
        // No "Person Filing" field in this form — petitioner identified by caption only
        'petitioner_full_name'      => 'Name of PetitionerParty A',
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of RespondentParty B',
        // Community creditors (up to 9 rows)
        'debt_creditor_1'           => 'Creditor NameRow1 DRA71f',
        'debt_creditor_2'           => 'Creditor NameRow2 DRA71f',
        'debt_creditor_3'           => 'Creditor NameRow3 DRA71f',
        'debt_creditor_4'           => 'Creditor NameRow4 DRA71f',
        'debt_creditor_5'           => 'Creditor NameRow5 DRA71f',
        'debt_creditor_6'           => 'Creditor NameRow6 DRA71f',
    ),

    // =========================================================================
    // WC — STEP 1 — Petition for Dissolution with Children (drdc15fz.pdf)
    // =========================================================================
    'wc_petition' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'PetitionerParty A', 'Name DRDC15f' ],
        'petitioner_address'        => [ 'Address if not protected', 'Address DRDC15f' ],
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => [ 'RespondentParty B', 'Name_2 DRDC15f' ],
        'respondent_address'        => 'Address_2 DRDC15f',
        'marriage_date'             => 'Date of Marriage DRDC15f',
        'marriage_city_state'       => 'City and state or country where we were married DRDC15f',
        'married_name_first'        => 'First name DRDC15f',
        'married_name_last'         => 'Last name DRDC15f',
        'restore_name_first'        => 'Restore first name DRDC15f',
        'restore_name_middle'       => 'Restore middle name DRDC15f',
        'restore_name_last'         => 'Restore last name DRDC15f',
        // Community debts (up to 6)
        'debt_description_1'        => 'DESCRIPTION OF DEBT 1 DRDC15f',
        'debt_responsible_a_1'      => 'A debt 1 DRDC15f',
        'debt_responsible_b_1'      => 'B debt 1 DRDC15f',
        'debt_description_2'        => 'DESCRIPTION OF DEBT 2 DRDC15f',
        'debt_responsible_a_2'      => 'A debt 2 DRDC15f',
        'debt_responsible_b_2'      => 'B debt 2 DRDC15f',
        'debt_description_3'        => 'DESCRIPTION OF DEBT 3 DRDC15f',
        'debt_responsible_a_3'      => 'A debt 3 DRDC15f',
        'debt_responsible_b_3'      => 'B debt 3 DRDC15f',
        'debt_description_4'        => 'DESCRIPTION OF DEBT 4 DRDC15f',
        'debt_responsible_a_4'      => 'A debt 4 DRDC15f',
        'debt_responsible_b_4'      => 'B debt 4 DRDC15f',
        'debt_description_5'        => 'DESCRIPTION OF DEBT 5 DRDC15f',
        'debt_responsible_a_5'      => 'A debt 5 DRDC15f',
        'debt_responsible_b_5'      => 'B debt 5 DRDC15f',
        'debt_description_6'        => 'DESCRIPTION OF DEBT 6 DRDC15f',
        'debt_responsible_a_6'      => 'A debt 6 DRDC15f',
        'debt_responsible_b_6'      => 'B debt 6 DRDC15f',
    ),

    // =========================================================================
    // WC — STEP 1 — Summons (dr11fz.pdf) — same form as WOC
    // =========================================================================
    'wc_summons' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Nameof Petitioner  Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case No',
        'respondent_full_name'      => [ 'Name of Respondent  Party B', 'Name of opposing party DR11f' ],
    ),

    // =========================================================================
    // WC — STEP 1 — Sensitive Data Cover Sheet WC (drsds10f-cz.pdf)
    // =========================================================================
    'wc_sensitive_data' => array(
        // Note: Email field is 'Email' (not 'Email Address') in this specific form
        'petitioner_full_name'      => [ 'Person Filing', 'Petitioner  Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Respondent  Party B',
    ),

    // =========================================================================
    // WC — STEP 1 — Preliminary Injunction (dr14fz.pdf) — same form as WOC
    // =========================================================================
    'wc_preliminary_injunction' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of PetitionerParty A', 'Name DR14f' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => [ 'Name of RespondentParty B', 'Name_2 DR14f' ],
    ),

    // =========================================================================
    // WC — STEP 3 — Application and Affidavit for Entry of Default (drd61fz.pdf)
    // =========================================================================
    'wc_default_application' => array(
        'petitioner_full_name'      => [ 'Person Filing', 'Name of Petitioner  Party A' ],
        'petitioner_address'        => 'Address if not protected',
        'petitioner_city_state_zip' => 'City State Zip Code',
        'petitioner_phone'          => 'Telephone',
        'petitioner_email'          => 'Email Address',
        'case_number'               => 'Case Number',
        'respondent_full_name'      => 'Name of Respondent  Party B',
        'respondent_address'        => 'the last known mailing address for the Party in default is DRD61f',
        'date_of_service'           => 'Date of Service DRD61f',
    ),
);

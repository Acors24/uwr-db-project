<?php

enum Result
{
    case ConnectionFailed;
    case QueryFailed;
    case MissingFirstName;
    case MissingLastName;
    case MissingPhoneNumber;
    case MissingEmail;
    case UnknownEmail;
    case WrongEmail;
    case UnavailableEmail;
    case MissingPassword;
    case WrongPassword;
    case MissingPasswordRe;
    case WrongPasswordRe;
    case NoStation;
    case NotBothEndStations;
    case SameStations;
    case NoStationConnection;
    case InvalidStation;
    case InvalidAmount;
    case NoneReserved;
    case InvalidDate;
    case InvalidInterval;
    case ReservedAmountExceeded;
    case MissingValues;
    case AmountExceeded;
    case InsufficientPermissions;
    case OK;
}

enum ReservationPhase
{
    case StationSelect;
    case ItemSelect;
    case Confirm;
    case Success;
}

enum TripCreationPhase
{
    case StationSelect;
    case PathSelect;
    case Success;
}

function get_message(Result $result)
{
    return match ($result) {
        Result::ConnectionFailed => "Database connection error.",
        Result::QueryFailed => "Database query error.",
        Result::MissingFirstName => "First name is required.",
        Result::MissingLastName => "Last name is required.",
        Result::MissingPhoneNumber => "Phone number is required.",
        Result::MissingEmail => "Email address is required.",
        Result::UnknownEmail => "Unknown email address.",
        Result::WrongEmail => "Wrong email address.",
        Result::UnavailableEmail => "This email is unavailable.",
        Result::MissingPassword => "Password is required.",
        Result::WrongPassword => "Wrong password.",
        Result::MissingPasswordRe => "Password confirmation is required.",
        Result::WrongPasswordRe => "Wrong password confirmation.",
        Result::NoStation => "Select at least one station.",
        Result::NotBothEndStations => "Select both stations.",
        Result::SameStations => "Selected stations cannot be the same.",
        Result::NoStationConnection => "There is no connection between selected stations.",
        Result::InvalidStation => "This station does not exist.",
        Result::InvalidAmount => "Reserved amounts cannot be negative.",
        Result::NoneReserved => "The reservation must include at least one item.",
        Result::InvalidDate => "Selected dates must be in the future.",
        Result::InvalidInterval => "The finish date cannot be before the start date.",
        Result::ReservedAmountExceeded => "The amount of reserved items cannot exceed the amount of available items, provided next to the name and price.",
        Result::AmountExceeded => "Exceeded the available amount.",
        Result::MissingValues => "Not all required values have been sent.",
        Result::InsufficientPermissions => "Insufficient permissions.",
        Result::OK => "OK",
        default => "Unknown error."
    };
}

?>
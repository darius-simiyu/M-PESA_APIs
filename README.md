# Implementing C2B M-PESA APIs
This project demonstrates a simple implementation of the C2B (Consumer to Business) Safaricom M-PESA mobile payment APIs.
A typical use case for this scenario is online payment.
Processes involved in online shopping system include:
1. Selection of products.
2. Calculation of total cost of shopping cart.
3. Offering user payment option.
4. Validating the payment.
5. Completing the purchase.
6. Saving completed purchases as transactions.
7. Delivering purchased products to consumer.

## How C2B API Works
1. You've to [register](https://developer.safaricom.co.ke/login-register) as a business or individual and create an app. There's an option ot create a sandboxed app, for testing purposes.
2. For the sandbox app, after successfully registration, you'll be given a **Consumer Key** and **Consumer Secret**. These pairs of hashed strings won't change for the same application.
3. We're all set.  

## Our Focus
We focus on the main core part of the system; checking out.
Obviously, there are several ways of implementing this. For simplicity, here are the steps that would be followed in implementation of this system.
1. Getting pre-requisites (Getting Consumer Key and Secret).
2. Building Application backend; defining database and generating dummy data to work with.
3. Building frontend.
4. Testing

### 1. Getting Consumer Key and Consumer Secret.
It's easy. Just follow the go to [Safaricom's registration page](https://developer.safaricom.co.ke/login-register), register and create add app.

### 2. Building Application backend
1. In a fresh Laravel application or framework of choice create database models for Product, Consumer, Purchase and Transaction.
2. Use a seeder of Faker library to generate some products data.

## What next?
Choose framework of choice and view typical implementation.
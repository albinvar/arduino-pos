import serial
import requests
import json
import sys
import time

# Serial port configuration
port = 'COM6'
baudrate = 9600

# Bearer token for authorization
bearer_token = 'yKTyfjfgpMoxB7eDBxzO3CeuHo4XX41BlHnOvmllc4b671f0'

# POS server configuration
pos_server_url = 'http://127.0.0.1:8000/api/'
headers = {
    'Content-Type': 'application/json',
    'Authorization': f'Bearer {bearer_token}'
}

# Function to send data to the main server
def send_to_server(data):
    try:
        response = requests.post(pos_server_url + 'transaction', json=data, headers=headers)
        if response.status_code == 200:
            print("Data sent successfully to server!")
            return True
        else:
            print("Failed to send data to server:", response.status_code)
            return False
    except Exception as e:
        print("Error:", e)
        return False

# Function to fetch balance from the POS server
def fetch_balance_from_server():
    try:
        response = requests.get(pos_server_url + 'balance', headers=headers)
        if response.status_code == 200:
            balance_data = response.json()
            return balance_data['balance']
        else:
            print("Failed to fetch balance from server:", response.status_code)
            return None
    except Exception as e:
        print("Error:", e)
        return None

# Function to validate RFID card
def validate_card(card_id):
    try:
        data = {'card_no': card_id}
        response = requests.post(pos_server_url + f'card', json=data, headers=headers)
        if response.status_code == 200:
            card_data = response.json()
            if card_data['status'] == 'success':
                print("Card validated successfully!")
                return card_data
            else:
                print("Invalid card! Retry card scan.")
                return None
        else:
            print("Failed to validate card:", response.status_code)
            return None
    except Exception as e:
        print("Error:", e)
        return None

# Initialize serial communication
try:
    ser = serial.Serial(port, baudrate)
    print("Serial port opened successfully!")
except serial.SerialException as e:
    print("Failed to open serial port:", e)
    sys.exit(1)

try:
    while True:
        # Read data from Arduino
        data = ser.readline().decode().strip()

        # Check if Arduino requests balance
        if data == 'check-balance':
            # Fetch balance from server
            balance = fetch_balance_from_server()

            # Send balance to Arduino
            if balance is not None:
                ser.write("Balance: {}".format(balance).encode())
            else:
                ser.write(b'Error fetching balance')

        # Check if Arduino requests to validate card
        if data == 'validate-card':
            # Read RFID tag from Arduino
            card_id = ser.readline().decode().strip()

            # Validate card with POS server
            card_data = validate_card(card_id)

            print(card_id)

            # Send card data to Arduino if valid
            if card_data is not None:
                ser.write("Valid card, pin_status: {}".format(card_data['pin_status']).encode())
            else:
                ser.write(b'Invalid card')
       
        if data == 'complete-transaction':
            # Read transaction data from Arduino
            pin = ser.readline().decode().strip()
            amount = ser.readline().decode().strip()
            card_id = ser.readline().decode().strip()

            # Prepare transaction data
            transaction_data = {
                'pin': pin,
                'amount': amount,
                'card_no': card_id
            }
            # Send transaction data to server
            res = send_to_server(transaction_data)
            if res:
                ser.write(b'Transaction successful!')
                print("Transaction completed successfully!")
            else:
                ser.write(b'Transaction failed!')
                print("Failed to complete transaction!")
            
        print(data)

        # Process the data as needed

except KeyboardInterrupt:
    print("Exiting script...")
    ser.close()  # Close the serial port before exiting
    sys.exit(0)

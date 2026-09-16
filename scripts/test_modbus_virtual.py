#!/usr/bin/env python3
"""Valida o transporte Modbus TCP do CLP virtual sem tocar em hardware."""
from __future__ import annotations
import os
import socket
import struct

HOST = os.getenv("MODBUS_TEST_HOST", "127.0.0.1")
PORT = int(os.getenv("MODBUS_TEST_PORT", "1502"))
UNIT = int(os.getenv("MODBUS_TEST_UNIT", "1"))

def request(sock: socket.socket, transaction: int, function: int, payload: bytes) -> bytes:
    frame = struct.pack(">HHHB", transaction, 0, len(payload) + 2, UNIT)
    sock.sendall(frame + bytes([function]) + payload)
    header = sock.recv(7)
    if len(header) != 7:
        raise RuntimeError("resposta Modbus incompleta")
    received_transaction, protocol, length, unit = struct.unpack(">HHHB", header)
    body = sock.recv(length - 1)
    if received_transaction != transaction or protocol != 0 or unit != UNIT:
        raise RuntimeError("cabeçalho Modbus inválido")
    if not body or body[0] != function:
        if body and body[0] == (function | 0x80):
            raise RuntimeError(f"exceção Modbus {body[1] if len(body) > 1 else '?'}")
        raise RuntimeError("função Modbus inesperada")
    return body

with socket.create_connection((HOST, PORT), timeout=3) as connection:
    request(connection, 1, 6, struct.pack(">HH", 0, 1234))
    holding = request(connection, 2, 3, struct.pack(">HH", 0, 1))
    if holding[1] != 2 or struct.unpack(">H", holding[2:4])[0] != 1234:
        raise RuntimeError("holding[0] não preservou a escrita")
    request(connection, 3, 5, struct.pack(">HH", 0, 0xFF00))
    coil = request(connection, 4, 1, struct.pack(">HH", 0, 1))
    if coil[1] != 1 or not (coil[2] & 1):
        raise RuntimeError("coil[0] não preservou a escrita")

print(f"OK: Modbus TCP virtual {HOST}:{PORT} leu e escreveu holding[0] e coil[0].")

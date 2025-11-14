#!/bin/bash

if ! command -v docker &> /dev/null
then
    echo "Docker is not installed"
    exit
fi

if ! docker system info &> /dev/null
then
    echo "Starting Docker"
    open /Applications/Docker.app
    while ! docker system info &> /dev/null
    do
        sleep 1
    done
    echo "Docker is now running"
else
    echo "Docker is already running"
fi

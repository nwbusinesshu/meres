#!/bin/bash
# API Test Script - Tests all endpoints and shows results
# Usage: bash api_test_all.sh

API_KEY="qa360_live_2yZThcy2ppZet5aUGogmVarh49nuxxLF"
BASE_URL="https://staging.nwbusiness.hu/api/v1"

echo "================================================"
echo "Quarma360 API Testing Script"
echo "================================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

test_endpoint() {
    local name=$1
    local endpoint=$2
    
    echo -e "${YELLOW}Testing: ${name}${NC}"
    echo "Endpoint: GET ${endpoint}"
    
    response=$(curl -s -w "\n%{http_code}" -X GET "${BASE_URL}${endpoint}" \
        -H "X-API-Key: ${API_KEY}" \
        -H "Accept: application/json")
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" == "200" ]; then
        echo -e "${GREEN}✓ Success (200)${NC}"
        echo "$body" | jq -C '.' 2>/dev/null || echo "$body"
    else
        echo -e "${RED}✗ Failed (${http_code})${NC}"
        echo "$body"
    fi
    
    echo ""
    echo "------------------------------------------------"
    echo ""
}

# Test 1: Organization Info
test_endpoint "Organization Info" "/organization"

# Test 2: Users List
test_endpoint "Users List" "/users"

# Test 3: Assessments List
test_endpoint "Assessments List" "/assessments"

# Test 4: Competencies List
test_endpoint "Competencies List" "/competencies"

# Test 5: Bonus-Malus Categories
test_endpoint "Bonus-Malus Categories" "/bonus-malus/categories"

# Get first user ID for detailed tests
echo "Getting first user ID for detailed tests..."
first_user_id=$(curl -s -X GET "${BASE_URL}/users" \
    -H "X-API-Key: ${API_KEY}" \
    -H "Accept: application/json" | jq -r '.data[0].id' 2>/dev/null)

if [ ! -z "$first_user_id" ] && [ "$first_user_id" != "null" ]; then
    echo "First user ID: ${first_user_id}"
    echo ""
    
    # Test 6: Specific User
    test_endpoint "Specific User Details" "/users/${first_user_id}"
    
    # Test 7: User Results
    test_endpoint "User Results History" "/results/user/${first_user_id}"
    
    # Test 8: User Bonus-Malus
    test_endpoint "User Bonus-Malus Status" "/bonus-malus/user/${first_user_id}"
fi

# Get first assessment ID for detailed tests
echo "Getting first assessment ID for detailed tests..."
first_assessment_id=$(curl -s -X GET "${BASE_URL}/assessments" \
    -H "X-API-Key: ${API_KEY}" \
    -H "Accept: application/json" | jq -r '.data[0].id' 2>/dev/null)

if [ ! -z "$first_assessment_id" ] && [ "$first_assessment_id" != "null" ]; then
    echo "First assessment ID: ${first_assessment_id}"
    echo ""
    
    # Test 9: Assessment Details
    test_endpoint "Assessment Details" "/assessments/${first_assessment_id}"
    
    # Test 10: Assessment Results
    test_endpoint "Assessment Results" "/assessments/${first_assessment_id}/results"
fi

echo "================================================"
echo "Testing Complete!"
echo "================================================"

# Test authentication error
echo ""
echo -e "${YELLOW}Testing Authentication Error (should fail):${NC}"
curl -s -X GET "${BASE_URL}/organization" \
    -H "X-API-Key: wrong_key_123" \
    -H "Accept: application/json" | jq -C '.' 2>/dev/null || curl -s -X GET "${BASE_URL}/organization" \
    -H "X-API-Key: wrong_key_123" \
    -H "Accept: application/json"
echo ""
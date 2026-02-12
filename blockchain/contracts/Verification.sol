// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract Verification {
    // ✅ Access Control
    address public owner;

    modifier onlyOwner() {
        require(msg.sender == owner, "Not authorized");
        _;
    }

    event OwnershipTransferred(address indexed previousOwner, address indexed newOwner);

    struct Proof {
        bytes32 dataHash;
        string category;
        string modelVersion;
        uint256 timestamp;
        address verifier;
    }

    mapping(uint256 => Proof) public proofs;

    event Verified(
        uint256 indexed reportId,
        bytes32 dataHash,
        string category,
        string modelVersion,
        uint256 timestamp,
        address verifier
    );

    constructor() {
        owner = msg.sender; // deployer becomes owner
        emit OwnershipTransferred(address(0), owner);
    }

    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "Zero address");
        address prev = owner;
        owner = newOwner;
        emit OwnershipTransferred(prev, newOwner);
    }

    // ✅ Only the owner (your server wallet) can write verifications
    function recordVerification(
        uint256 reportId,
        bytes32 dataHash,
        string calldata category,
        string calldata modelVersion
    ) external onlyOwner {
        proofs[reportId] = Proof({
            dataHash: dataHash,
            category: category,
            modelVersion: modelVersion,
            timestamp: block.timestamp,
            verifier: msg.sender
        });

        emit Verified(reportId, dataHash, category, modelVersion, block.timestamp, msg.sender);
    }

    function getProof(uint256 reportId) external view returns (
        bytes32 dataHash,
        string memory category,
        string memory modelVersion,
        uint256 timestamp,
        address verifier
    ) {
        Proof memory p = proofs[reportId];
        return (p.dataHash, p.category, p.modelVersion, p.timestamp, p.verifier);
    }
}

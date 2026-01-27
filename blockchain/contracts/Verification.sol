// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract Verification {
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

    function recordVerification(
        uint256 reportId,
        bytes32 dataHash,
        string calldata category,
        string calldata modelVersion
    ) external {
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
